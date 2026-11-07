<?php

namespace App\Servicios;

use App\Models\Cobro;
use App\Models\Cuota;
use App\Models\TipoCambio;
use App\Models\Venta;
use App\Support\Formato;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PBI-07 — cobro de cuotas (CA-07.1 a 07.4).
 *
 * - El pago puede venir en Bs o USD; se convierte a la moneda de la venta con el TC oficial
 *   del día (o el pactado, si la venta lo dice). Cambiar el TC oficial exige motivo (HP-02).
 * - El monto se aplica a la cuota más antigua con saldo (CalculadoraDePlan::aplicarPago).
 * - Un QR queda «por confirmar»: reserva su parte de la cuota, pero no cuenta como pagado
 *   (ni en la cartera) hasta que un usuario autorizado lo confirma.
 * - cuotas.pagado se recalcula siempre desde los cobros confirmados y no anulados.
 */
final class Cobros
{
    public function __construct(private readonly CalculadoraDePlan $calculadora = new CalculadoraDePlan)
    {
    }

    /**
     * @param  array{medio:string, fecha_pago?:?string, moneda_pago:string, monto:string, tc?:?string, motivo_tc?:?string, comprobante_path?:?string}  $datos
     */
    public function registrar(Venta $venta, array $datos): Cobro
    {
        return DB::transaction(function () use ($venta, $datos) {
            // Bloquea las cuotas de la venta: dos cajeros no pueden cobrar la misma cuota a la vez.
            $cuotas = Cuota::where('venta_id', $venta->id)->orderBy('numero')->lockForUpdate()->get();

            if (CalculadoraDePlan::aCentavos($datos['monto']) <= 0) {
                throw ValidationException::withMessages(['monto' => 'El monto debe ser mayor a cero.']);
            }
            $fechaPago = \Carbon\CarbonImmutable::parse($datos['fecha_pago'] ?? today());
            if ($fechaPago->gt(today())) {
                throw ValidationException::withMessages(['fecha_pago' => 'La fecha del pago no puede ser futura.']);
            }

            if ($datos['medio'] === 'qr' && empty($datos['comprobante_path'])) {
                throw ValidationException::withMessages(['comprobante' => 'Adjunta la captura del pago por QR.']);
            }

            [$tc, $fuente, $motivoTc] = $this->tipoDeCambio($venta, $datos);
            $aplicado = $this->convertir($datos['monto'], $datos['moneda_pago'], $venta->moneda, $tc);

            if (CalculadoraDePlan::aCentavos($aplicado) <= 0) {
                throw ValidationException::withMessages(['monto' => 'El monto es demasiado pequeño.']);
            }

            $saldos = $this->saldosDisponibles($cuotas);
            $reparto = $this->calculadora->aplicarPago($saldos, $aplicado);

            if (CalculadoraDePlan::aCentavos($reparto['excedente']) > 0) {
                $total = CalculadoraDePlan::aTexto(array_sum(array_map([CalculadoraDePlan::class, 'aCentavos'], $saldos)));
                throw ValidationException::withMessages([
                    'monto' => "El pago ({$venta->simboloMoneda()} ".Formato::monto($aplicado).") supera el saldo pendiente de {$venta->simboloMoneda()} ".Formato::monto($total).'.',
                ]);
            }

            $cobro = Cobro::create([
                'negocio_id' => $venta->negocio_id,
                'venta_id' => $venta->id,
                'user_id' => auth()->id(),
                'medio' => $datos['medio'],
                'fecha_pago' => $fechaPago->toDateString(),
                'moneda_pago' => $datos['moneda_pago'],
                'monto_pago' => $datos['monto'],
                'tc_aplicado' => $tc,
                'fuente_tc' => $fuente,
                'motivo_tc' => $motivoTc,
                'monto_aplicado' => $aplicado,
                'estado' => $datos['medio'] === 'qr' ? 'por_confirmar' : 'confirmado',
                'comprobante_path' => $datos['comprobante_path'] ?? null,
            ]);

            foreach ($reparto['aplicado'] as $cuotaId => $monto) {
                $cobro->cuotas()->attach($cuotaId, ['monto' => $monto]);
            }

            $this->recalcular($venta);

            return $cobro;
        });
    }

    /** cuotas.pagado = suma de lo aplicado por cobros confirmados y no anulados. */
    public function recalcular(Venta $venta): void
    {
        DB::update(<<<'SQL'
            UPDATE cuotas SET pagado = COALESCE((
                SELECT SUM(cc.monto) FROM cobro_cuota cc
                JOIN cobros c ON c.id = cc.cobro_id
                WHERE cc.cuota_id = cuotas.id AND c.estado = 'confirmado' AND c.anulado_at IS NULL
            ), 0), updated_at = NOW()
            WHERE venta_id = ?
        SQL, [$venta->id]);

        $venta->unsetRelation('cuotas');
    }

    /** Vista previa para la pantalla: cuánto equivale y a qué cuotas se aplicaría. */
    public function simular(Venta $venta, array $datos): array
    {
        [$tc, $fuente] = $this->tipoDeCambio($venta, $datos);
        $aplicado = $this->convertir($datos['monto'], $datos['moneda_pago'], $venta->moneda, $tc);
        $cuotas = $venta->cuotas()->get();
        $reparto = $this->calculadora->aplicarPago($this->saldosDisponibles($cuotas), $aplicado);

        return [
            'aplicado' => $aplicado,
            'tc' => $tc,
            'fuente' => $fuente,
            'excedente' => $reparto['excedente'],
            'reparto' => collect($reparto['aplicado'])->map(function ($monto, $cuotaId) use ($cuotas) {
                $cuota = $cuotas->firstWhere('id', $cuotaId);
                $queda = CalculadoraDePlan::aCentavos($cuota->saldo()) - CalculadoraDePlan::aCentavos($monto);

                return ['numero' => $cuota->numero, 'monto' => $monto, 'completa' => $queda <= 0];
            })->values()->all(),
        ];
    }

    // ---------------------------------------------------------------- ayudas

    /**
     * Saldo de cada cuota descontando también lo reservado por QR por confirmar.
     *
     * @return array<int, string> cuota_id => saldo
     */
    private function saldosDisponibles($cuotas): array
    {
        $reservado = DB::table('cobro_cuota')
            ->join('cobros', 'cobros.id', '=', 'cobro_cuota.cobro_id')
            ->whereIn('cobro_cuota.cuota_id', $cuotas->pluck('id'))
            ->where('cobros.estado', 'por_confirmar')
            ->whereNull('cobros.anulado_at')
            ->groupBy('cobro_cuota.cuota_id')
            ->selectRaw('cobro_cuota.cuota_id, SUM(cobro_cuota.monto) AS reservado')
            ->pluck('reservado', 'cuota_id');

        $saldos = [];
        foreach ($cuotas as $cuota) {
            $saldo = CalculadoraDePlan::aCentavos($cuota->saldo())
                - CalculadoraDePlan::aCentavos((string) ($reservado[$cuota->id] ?? '0'));
            $saldos[$cuota->id] = CalculadoraDePlan::aTexto(max(0, $saldo));
        }

        return $saldos;
    }

    /**
     * @return array{0:?string, 1:?string, 2:?string} [tc, fuente, motivo]
     */
    private function tipoDeCambio(Venta $venta, array $datos): array
    {
        if ($datos['moneda_pago'] === $venta->moneda) {
            return [null, null, null];
        }

        if ($venta->regla_tc === 'pactado') {
            return [(string) $venta->tc_pactado, 'Pactado en la venta', null];
        }

        $oficial = TipoCambio::vigente();
        if (! $oficial) {
            throw ValidationException::withMessages(['tc' => 'Registra primero el tipo de cambio del día.']);
        }

        $escrito = trim((string) ($datos['tc'] ?? ''));
        if ($escrito === '' || CalculadoraDePlan::aDiezmilesimos($escrito) === CalculadoraDePlan::aDiezmilesimos((string) $oficial->valor)) {
            return [(string) $oficial->valor, "{$oficial->fuente} del {$oficial->fecha->format('d/m/Y')}", null];
        }

        $motivo = trim((string) ($datos['motivo_tc'] ?? ''));
        if (mb_strlen($motivo) < 5) {
            throw ValidationException::withMessages(['motivo_tc' => 'Cambiaste el tipo de cambio oficial: indica el motivo.']);
        }

        return [$escrito, 'Modificado por el cajero', $motivo];
    }

    private function convertir(string $monto, string $de, string $a, ?string $tc): string
    {
        if ($de === $a) {
            return CalculadoraDePlan::aTexto(CalculadoraDePlan::aCentavos($monto));
        }

        return $de === 'BOB'
            ? $this->calculadora->bsAUsd($monto, $tc)
            : $this->calculadora->usdABs($monto, $tc);
    }
}
