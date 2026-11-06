<?php

namespace App\Servicios;

use App\Models\Comprador;
use App\Models\Cuota;
use App\Models\Reserva;
use App\Models\Venta;
use App\Models\Vehiculo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Reglas de negocio de reservas (PBI-05) y ventas (PBI-06).
 * Las pantallas validan el formato; aquí se validan los estados y los montos.
 */
final class Ventas
{
    public function __construct(private readonly CalculadoraDePlan $calculadora = new CalculadoraDePlan)
    {
    }

    // ---------------------------------------------------------------- reservas (PBI-05)

    /**
     * CA-05: reserva con anticipo y fecha límite; no se puede reservar un vehículo reservado.
     *
     * @param  array{ci:string, nombre:string, telefono?:?string}  $comprador
     */
    public function reservar(Vehiculo $vehiculo, array $comprador, string $anticipo, string $moneda, string $fechaLimite): Reserva
    {
        return DB::transaction(function () use ($vehiculo, $comprador, $anticipo, $moneda, $fechaLimite) {
            $vehiculo = Vehiculo::whereKey($vehiculo->id)->lockForUpdate()->firstOrFail();

            if ($vehiculo->estado !== 'en_exhibicion') {
                throw ValidationException::withMessages([
                    'vehiculo' => "El vehículo está «{$vehiculo->nombreEstado()}» y no se puede reservar.",
                ]);
            }
            if (CalculadoraDePlan::aCentavos($anticipo) <= 0) {
                throw ValidationException::withMessages(['anticipo' => 'El anticipo debe ser mayor a cero.']);
            }
            if (CarbonImmutable::parse($fechaLimite)->lt(today())) {
                throw ValidationException::withMessages(['fecha_limite' => 'La fecha límite no puede estar en el pasado.']);
            }

            $reserva = Reserva::create([
                'vehiculo_id' => $vehiculo->id,
                'comprador_id' => $this->comprador($comprador)->id,
                'user_id' => auth()->id(),
                'anticipo' => $anticipo,
                'moneda' => $moneda,
                'fecha_limite' => $fechaLimite,
                'estado' => 'activa',
            ]);

            $vehiculo->update(['estado' => 'reservado']);

            return $reserva;
        });
    }

    public function cancelarReserva(Reserva $reserva): void
    {
        DB::transaction(function () use ($reserva) {
            if ($reserva->estado !== 'activa') {
                throw ValidationException::withMessages(['reserva' => 'Solo se puede cancelar una reserva activa.']);
            }
            $reserva->update(['estado' => 'cancelada']);
            $this->liberar($reserva->vehiculo);
        });
    }

    /**
     * CA-05: una reserva vencida devuelve el vehículo a «En exhibición».
     * Se ejecuta al abrir el listado de vehículos y el panel (sin tareas programadas).
     */
    public function vencerReservas(): int
    {
        $vencidas = Reserva::with('vehiculo')
            ->where('estado', 'activa')
            ->whereDate('fecha_limite', '<', today())
            ->get();

        foreach ($vencidas as $reserva) {
            DB::transaction(function () use ($reserva) {
                $reserva->update(['estado' => 'vencida']);
                $this->liberar($reserva->vehiculo);
            });
        }

        return $vencidas->count();
    }

    // ---------------------------------------------------------------- ventas (PBI-06)

    /**
     * Registra una venta al contado o a crédito.
     * CA-06.1: genera el plan y el vehículo pasa a «Vendido».
     * CA-06.2: si la inicial más las cuotas no iguala el precio, no guarda y muestra la diferencia.
     * CA-06.3: un vehículo ya vendido se rechaza indicando su estado.
     *
     * @param  array<string, mixed>  $datos
     */
    public function vender(Vehiculo $vehiculo, array $datos): Venta
    {
        return DB::transaction(function () use ($vehiculo, $datos) {
            $vehiculo = Vehiculo::whereKey($vehiculo->id)->lockForUpdate()->firstOrFail();

            if (! in_array($vehiculo->estado, ['en_exhibicion', 'reservado'], true)) {
                throw ValidationException::withMessages([
                    'vehiculo' => "El vehículo ya está «{$vehiculo->nombreEstado()}»; no se puede vender de nuevo.",
                ]);
            }

            $reserva = $vehiculo->reservaActiva()->with('comprador')->first();
            $ci = self::normalizarCi($datos['comprador']['ci']);

            if ($reserva && $reserva->comprador->ci !== $ci) {
                throw ValidationException::withMessages([
                    'comprador.ci' => "El vehículo está reservado para {$reserva->comprador->nombre} (CI {$reserva->comprador->ci}) hasta el {$reserva->fecha_limite->format('d/m/Y')}.",
                ]);
            }

            $moneda = $datos['moneda'] ?? 'USD';
            $regla = $datos['regla_tc'] ?? 'oficial';
            $tc = ($datos['tc_pactado'] ?? '') !== '' ? $datos['tc_pactado'] : null;

            if (($moneda === 'BOB' || $regla === 'pactado') && $tc === null) {
                throw ValidationException::withMessages(['tc_pactado' => 'Indica el tipo de cambio pactado.']);
            }

            $precio = $datos['precio'];
            $credito = $datos['tipo'] === 'credito';
            $cuotas = $credito ? $this->planDeCuotas($precio, $datos) : [];

            $venta = Venta::create([
                'vehiculo_id' => $vehiculo->id,
                'comprador_id' => $this->comprador($datos['comprador'])->id,
                'reserva_id' => $reserva?->id,
                'user_id' => auth()->id(),
                'tipo' => $datos['tipo'],
                'moneda' => $moneda,
                'regla_tc' => $regla,
                'tc_pactado' => $tc,
                'precio' => $precio,
                'inicial' => $credito ? $datos['inicial'] : $precio,
                'numero_cuotas' => count($cuotas),
                'fecha_venta' => $datos['fecha_venta'] ?? today(),
            ]);

            foreach ($cuotas as $cuota) {
                $venta->cuotas()->create([
                    'negocio_id' => $venta->negocio_id,
                    'numero' => $cuota['numero'],
                    'vencimiento' => $cuota['vencimiento'],
                    'monto' => $cuota['monto'],
                ]);
            }

            $reserva?->update(['estado' => 'convertida']);
            $vehiculo->update(['estado' => 'vendido']);

            return $venta;
        });
    }

    /**
     * Genera el plan con la calculadora del PBI-01 y comprueba que la cuota escrita cuadre.
     *
     * @return list<array{numero:int, vencimiento:CarbonImmutable, monto:string}>
     */
    private function planDeCuotas(string $precio, array $datos): array
    {
        $inicial = $datos['inicial'];
        $n = (int) $datos['numero_cuotas'];
        $cuotaEscrita = $datos['monto_cuota'];

        try {
            $plan = $this->calculadora->generarCuotas($precio, $inicial, $n, CarbonImmutable::parse($datos['primer_vencimiento']));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['inicial' => $e->getMessage()]);
        }

        if (CalculadoraDePlan::aCentavos($plan[0]['monto']) !== CalculadoraDePlan::aCentavos($cuotaEscrita)) {
            $suma = CalculadoraDePlan::aCentavos($inicial) + $n * CalculadoraDePlan::aCentavos($cuotaEscrita);
            $diferencia = CalculadoraDePlan::aCentavos($precio) - $suma;

            throw ValidationException::withMessages([
                'monto_cuota' => sprintf(
                    'La inicial más las cuotas suman %s y el precio es %s: %s %s. Usa una cuota de %s.',
                    \App\Support\Formato::monto(CalculadoraDePlan::aTexto($suma)),
                    \App\Support\Formato::monto($precio),
                    $diferencia > 0 ? 'faltan' : 'sobran',
                    \App\Support\Formato::monto(CalculadoraDePlan::aTexto(abs($diferencia))),
                    \App\Support\Formato::monto($plan[0]['monto']),
                ),
            ]);
        }

        return $plan;
    }

    // ---------------------------------------------------------------- ayudas

    /** @param  array{ci:string, nombre:string, telefono?:?string}  $datos */
    private function comprador(array $datos): Comprador
    {
        return Comprador::firstOrCreate(
            ['ci' => self::normalizarCi($datos['ci'])],
            ['nombre' => trim($datos['nombre']), 'telefono' => $datos['telefono'] ?? null],
        );
    }

    private function liberar(Vehiculo $vehiculo): void
    {
        if ($vehiculo->estado === 'reservado') {
            $vehiculo->update(['estado' => 'en_exhibicion']);
        }
    }

    public static function normalizarCi(string $ci): string
    {
        return mb_strtoupper(preg_replace('/\s+/', '', $ci));
    }
}
