<?php

namespace App\Servicios;

use App\Importacion\LectorCarga;
use App\Importacion\PlantillaCarga;
use App\Models\Vehiculo;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;

/**
 * PBI-13 (CA-13): carga desde la plantilla los vehículos y las ventas a crédito vigentes.
 * Cada fila se guarda por separado: las inválidas se reportan y las válidas no se pierden.
 * Los chasis duplicados se detectan contra la base y dentro del mismo archivo.
 */
final class CargaInicial
{
    public function __construct(
        private readonly Ventas $ventas = new Ventas,
        private readonly Cobros $cobros = new Cobros,
        private readonly CalculadoraDePlan $calculadora = new CalculadoraDePlan,
    ) {
    }

    /**
     * @return array{vehiculos:int, ventas:int, errores: list<array{hoja:string, fila:int, mensaje:string}>}
     */
    public function procesar(string|UploadedFile $archivo): array
    {
        $hojas = Excel::toArray(new LectorCarga, $archivo);
        $resultado = ['vehiculos' => 0, 'ventas' => 0, 'errores' => []];

        foreach ($hojas[PlantillaCarga::HOJA_VEHICULOS] ?? [] as $i => $fila) {
            $this->fila($resultado, PlantillaCarga::HOJA_VEHICULOS, $i + 2, fn () => $this->vehiculo($fila), 'vehiculos', $fila);
        }

        foreach ($hojas[PlantillaCarga::HOJA_VENTAS] ?? [] as $i => $fila) {
            $this->fila($resultado, PlantillaCarga::HOJA_VENTAS, $i + 2, fn () => $this->venta($fila), 'ventas', $fila);
        }

        return $resultado;
    }

    private function fila(array &$resultado, string $hoja, int $numero, callable $guardar, string $contador, array $fila): void
    {
        if (collect($fila)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
            return; // fila vacía
        }

        try {
            DB::transaction($guardar);
            $resultado[$contador]++;
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->all() as $mensaje) {
                $resultado['errores'][] = ['hoja' => $hoja, 'fila' => $numero, 'mensaje' => $mensaje];
            }
        } catch (\InvalidArgumentException $e) {
            $resultado['errores'][] = ['hoja' => $hoja, 'fila' => $numero, 'mensaje' => $e->getMessage()];
        }
    }

    private function vehiculo(array $fila): void
    {
        $datos = [
            'chasis' => Vehiculo::normalizarChasis((string) ($fila['chasis'] ?? '')),
            'placa' => trim((string) ($fila['placa'] ?? '')) ?: null,
            'marca' => trim((string) ($fila['marca'] ?? '')),
            'modelo' => trim((string) ($fila['modelo'] ?? '')),
            'anio' => $fila['anio'] ?? null,
            'color' => trim((string) ($fila['color'] ?? '')) ?: null,
            'costo_usd' => self::monto($fila['costo_usd'] ?? null),
            'precio_usd' => self::monto($fila['precio_usd'] ?? null),
        ];

        $monto = ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
        Validator::make($datos, [
            'chasis' => ['required', 'min:5', 'max:30', Rule::unique('vehiculos', 'chasis')->where('negocio_id', auth()->user()->negocio_id)],
            'marca' => ['required', 'max:60'],
            'modelo' => ['required', 'max:60'],
            'anio' => ['required', 'integer', 'between:1950,'.(now()->year + 1)],
            'costo_usd' => $monto,
            'precio_usd' => $monto,
        ], [
            'chasis.unique' => "El chasis {$datos['chasis']} ya está registrado.",
        ], ['anio' => 'año', 'costo_usd' => 'costo', 'precio_usd' => 'precio'])->validate();

        Vehiculo::create($datos);
    }

    private function venta(array $fila): void
    {
        $chasis = Vehiculo::normalizarChasis((string) ($fila['chasis'] ?? ''));
        $datos = [
            'chasis' => $chasis,
            'ci_comprador' => trim((string) ($fila['ci_comprador'] ?? '')),
            'nombre_comprador' => trim((string) ($fila['nombre_comprador'] ?? '')),
            'moneda' => strtoupper(trim((string) ($fila['moneda'] ?? 'USD'))) ?: 'USD',
            'fecha_venta' => self::fecha($fila['fecha_venta'] ?? null),
            'primer_vencimiento' => self::fecha($fila['primer_vencimiento'] ?? null),
            'precio' => self::monto($fila['precio'] ?? null),
            'inicial' => self::monto($fila['inicial'] ?? null),
            'numero_cuotas' => $fila['numero_cuotas'] ?? null,
            'cuotas_pagadas' => $fila['cuotas_pagadas'] ?? 0,
        ];

        $monto = ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
        Validator::make($datos, [
            'chasis' => ['required'],
            'ci_comprador' => ['required', 'max:20'],
            'nombre_comprador' => ['required', 'max:255'],
            'moneda' => ['required', Rule::in(['USD'])],
            'fecha_venta' => ['required', 'date', 'before_or_equal:today'],
            'primer_vencimiento' => ['required', 'date'],
            'precio' => $monto,
            'inicial' => $monto,
            'numero_cuotas' => ['required', 'integer', 'between:1,120'],
            'cuotas_pagadas' => ['integer', 'min:0', 'lt:numero_cuotas'],
        ], [
            'moneda.in' => 'La carga inicial acepta ventas en USD.',
            'cuotas_pagadas.lt' => 'Las cuotas pagadas deben ser menos que el total: una venta pagada por completo no es vigente.',
        ], [
            'ci_comprador' => 'CI', 'nombre_comprador' => 'comprador', 'fecha_venta' => 'fecha de venta',
            'primer_vencimiento' => 'primer vencimiento', 'numero_cuotas' => 'número de cuotas', 'cuotas_pagadas' => 'cuotas pagadas',
        ])->validate();

        $vehiculo = Vehiculo::where('chasis', $chasis)->first();
        if (! $vehiculo) {
            throw ValidationException::withMessages(['chasis' => "No existe un vehículo con chasis {$chasis}: agrégalo en la hoja Vehiculos."]);
        }

        $n = (int) $datos['numero_cuotas'];
        $plan = $this->calculadora->generarCuotas($datos['precio'], $datos['inicial'], $n, CarbonImmutable::parse($datos['primer_vencimiento']));

        $venta = $this->ventas->vender($vehiculo, [
            'comprador' => ['ci' => $datos['ci_comprador'], 'nombre' => $datos['nombre_comprador'], 'telefono' => trim((string) ($fila['telefono'] ?? '')) ?: null],
            'tipo' => 'credito',
            'moneda' => 'USD',
            'regla_tc' => 'oficial',
            'precio' => $datos['precio'],
            'inicial' => $datos['inicial'],
            'numero_cuotas' => $n,
            'monto_cuota' => $plan[0]['monto'],
            'primer_vencimiento' => $datos['primer_vencimiento'],
            'fecha_venta' => $datos['fecha_venta'],
        ]);

        // Las cuotas que ya se pagaron antes de usar AutoNexo entran como un cobro de "carga inicial".
        $pagadas = (int) $datos['cuotas_pagadas'];
        if ($pagadas > 0) {
            $total = array_sum(array_map(fn ($c) => CalculadoraDePlan::aCentavos($c['monto']), array_slice($plan, 0, $pagadas)));
            $this->cobros->registrar($venta, [
                'medio' => 'carga_inicial',
                'moneda_pago' => 'USD',
                'monto' => CalculadoraDePlan::aTexto($total),
            ]);
        }
    }

    /** Excel puede entregar montos como número: se normalizan a texto con 2 decimales. */
    private static function monto(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_int($valor)) {
            return $valor.'.00';
        }
        if (is_float($valor)) {
            return number_format(round($valor, 2), 2, '.', '');
        }

        $texto = str_replace([' ', 'USD', 'Bs'], '', (string) $valor);

        return str_contains($texto, ',') && ! str_contains($texto, '.') ? str_replace(',', '.', $texto) : $texto;
    }

    /** Acepta fechas de Excel (número), "2026-06-05" o "05/06/2026". */
    private static function fecha(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_numeric($valor)) {
            return CarbonImmutable::instance(FechaExcel::excelToDateTimeObject((float) $valor))->toDateString();
        }
        $texto = trim((string) $valor);
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $texto, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return $texto;
    }
}
