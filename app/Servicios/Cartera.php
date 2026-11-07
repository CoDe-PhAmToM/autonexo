<?php

namespace App\Servicios;

use App\Models\Cobro;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PBI-08 (CA-08): la cartera suma los saldos pendientes confirmados y los clasifica por
 * tramos de antigüedad; los QR por confirmar se muestran aparte.
 *
 * Los saldos salen de cuotas.monto - cuotas.pagado, y "pagado" solo incluye cobros
 * confirmados y no anulados (ver Cobros::recalcular), así que un QR pendiente no descuenta.
 * Se calcula con SQL agregado para que sea rápido aun con miles de cuotas.
 */
final class Cartera
{
    public const TRAMOS = [
        'por_vencer' => 'Por vencer',
        'd1_30' => '1 a 30 días',
        'd31_60' => '31 a 60 días',
        'd61_mas' => 'Más de 60 días',
    ];

    /**
     * Totales por moneda y tramo, en texto con 2 decimales.
     *
     * @return array<string, array{total:string, vencido:string, tramos: array<string,string>, cuotas:int}>  clave: USD | BOB
     */
    public function resumen(int $negocioId): array
    {
        $filas = DB::select(<<<'SQL'
            SELECT v.moneda,
                   CASE
                       WHEN c.vencimiento >= CAST(:hoy AS date) THEN 'por_vencer'
                       WHEN CAST(:hoy AS date) - c.vencimiento <= 30 THEN 'd1_30'
                       WHEN CAST(:hoy AS date) - c.vencimiento <= 60 THEN 'd31_60'
                       ELSE 'd61_mas'
                   END AS tramo,
                   SUM(c.monto - c.pagado) AS saldo,
                   COUNT(*) AS cuotas
            FROM cuotas c
            JOIN ventas v ON v.id = c.venta_id
            WHERE c.negocio_id = :negocio AND c.monto > c.pagado
            GROUP BY v.moneda, tramo
        SQL, ['hoy' => today()->toDateString(), 'negocio' => $negocioId]);

        $resumen = [];
        foreach ($filas as $fila) {
            $resumen[$fila->moneda] ??= ['tramos' => array_fill_keys(array_keys(self::TRAMOS), 0), 'cuotas' => 0];
            $resumen[$fila->moneda]['tramos'][$fila->tramo] = CalculadoraDePlan::aCentavos((string) $fila->saldo);
            $resumen[$fila->moneda]['cuotas'] += (int) $fila->cuotas;
        }

        foreach ($resumen as $moneda => $datos) {
            $total = array_sum($datos['tramos']);
            $vencido = $total - $datos['tramos']['por_vencer'];
            $resumen[$moneda] = [
                'total' => CalculadoraDePlan::aTexto($total),
                'vencido' => CalculadoraDePlan::aTexto($vencido),
                'tramos' => array_map([CalculadoraDePlan::class, 'aTexto'], $datos['tramos']),
                'cuotas' => $datos['cuotas'],
            ];
        }

        ksort($resumen); // BOB antes que USD; la vista muestra USD primero

        return $resumen;
    }

    /**
     * Compradores con cuotas vencidas, del más atrasado al menos atrasado.
     *
     * @return Collection<int, object>
     */
    public function enMora(int $negocioId): Collection
    {
        return collect(DB::select(<<<'SQL'
            SELECT v.id AS venta_id, v.moneda, co.nombre, co.ci, co.telefono,
                   ve.marca, ve.modelo, ve.anio, ve.placa,
                   COUNT(*) AS cuotas_vencidas,
                   SUM(c.monto - c.pagado) AS saldo_vencido,
                   MAX(CAST(:hoy AS date) - c.vencimiento) AS dias_atraso
            FROM cuotas c
            JOIN ventas v ON v.id = c.venta_id
            JOIN compradores co ON co.id = v.comprador_id
            JOIN vehiculos ve ON ve.id = v.vehiculo_id
            WHERE c.negocio_id = :negocio AND c.monto > c.pagado AND c.vencimiento < CAST(:hoy AS date)
            GROUP BY v.id, v.moneda, co.nombre, co.ci, co.telefono, ve.marca, ve.modelo, ve.anio, ve.placa
            ORDER BY dias_atraso DESC
        SQL, ['hoy' => today()->toDateString(), 'negocio' => $negocioId]));
    }

    /** CA-08: los QR por confirmar se muestran aparte (no forman parte de lo cobrado). */
    public function qrPorConfirmar(): Collection
    {
        return Cobro::with('venta.comprador')
            ->where('estado', 'por_confirmar')
            ->whereNull('anulado_at')
            ->oldest('id')
            ->get();
    }
}
