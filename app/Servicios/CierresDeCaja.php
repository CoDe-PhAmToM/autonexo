<?php

namespace App\Servicios;

use App\Models\CierreCaja;
use App\Models\Cobro;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PBI-22 (CA-22): al cerrar la caja se comparan los cobros registrados del día con el
 * efectivo y los QR declarados; las diferencias quedan registradas.
 */
final class CierresDeCaja
{
    /**
     * Lo que el cajero registró en el sistema ese día, por medio y moneda (sin anulados
     * ni cargas iniciales). Los QR cuentan aunque estén por confirmar: el cajero los recibió.
     *
     * @return array{montos: array<string,string>, cobros:int}
     */
    public function registrado(User $cajero, CarbonInterface $fecha): array
    {
        $filas = Cobro::query()
            ->where('user_id', $cajero->id)
            ->whereDate('created_at', $fecha->toDateString())
            ->whereNull('anulado_at')
            ->whereIn('medio', ['efectivo', 'qr'])
            ->groupBy('medio', 'moneda_pago')
            ->selectRaw('medio, moneda_pago, SUM(monto_pago) AS total, COUNT(*) AS cantidad')
            ->get();

        $montos = array_fill_keys(array_keys(CierreCaja::COMBOS), '0.00');
        $cantidad = 0;
        foreach ($filas as $fila) {
            $montos[$fila->medio.'_'.strtolower($fila->moneda_pago)] = CalculadoraDePlan::aTexto(CalculadoraDePlan::aCentavos((string) $fila->total));
            $cantidad += (int) $fila->cantidad;
        }

        return ['montos' => $montos, 'cobros' => $cantidad];
    }

    /**
     * @param  array<string, string>  $declarado  combo => monto contado
     */
    public function cerrar(User $cajero, array $declarado, ?string $observacion = null): CierreCaja
    {
        return DB::transaction(function () use ($cajero, $declarado, $observacion) {
            $hoy = today();

            if (CierreCaja::where('user_id', $cajero->id)->whereDate('fecha', $hoy)->exists()) {
                throw ValidationException::withMessages(['cierre' => 'Ya cerraste la caja de hoy.']);
            }

            $registrado = $this->registrado($cajero, $hoy);
            $datos = ['user_id' => $cajero->id, 'fecha' => $hoy, 'cobros' => $registrado['cobros']];
            $hayDiferencia = false;

            foreach (array_keys(CierreCaja::COMBOS) as $combo) {
                $reg = CalculadoraDePlan::aCentavos($registrado['montos'][$combo]);
                $dec = CalculadoraDePlan::aCentavos($declarado[$combo] ?? '0');
                $datos["{$combo}_registrado"] = CalculadoraDePlan::aTexto($reg);
                $datos["{$combo}_declarado"] = CalculadoraDePlan::aTexto($dec);
                $datos["{$combo}_diferencia"] = CalculadoraDePlan::aTexto($dec - $reg);
                $hayDiferencia = $hayDiferencia || $dec !== $reg;
            }

            if ($hayDiferencia && mb_strlen(trim((string) $observacion)) < 5) {
                throw ValidationException::withMessages(['observacion' => 'Hay diferencias: explica qué pasó en la observación.']);
            }

            $datos['observacion'] = trim((string) $observacion) ?: null;

            return CierreCaja::create($datos);
        });
    }
}
