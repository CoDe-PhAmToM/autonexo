<?php

namespace App\Importacion;

use App\Models\Cobro;
use App\Models\Cuota;
use App\Models\Repuesto;
use App\Models\Venta;
use App\Models\Vehiculo;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * PBI-21: exportación de los registros del negocio a Excel (solo el dueño).
 * Los modelos ya filtran por el negocio del usuario autenticado.
 */
class RegistrosDelNegocio implements Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new HojaPlantilla('Vehiculos', ['chasis', 'placa', 'marca', 'modelo', 'anio', 'color', 'costo_usd', 'precio_usd', 'estado'],
                Vehiculo::orderBy('id')->get()->map(fn ($v) => [$v->chasis, $v->placa, $v->marca, $v->modelo, $v->anio, $v->color, $v->costo_usd, $v->precio_usd, $v->nombreEstado()])->all()),

            new HojaPlantilla('Ventas', ['venta', 'fecha', 'comprador', 'ci', 'chasis', 'tipo', 'moneda', 'precio', 'inicial', 'cuotas', 'saldo'],
                Venta::with('comprador', 'vehiculo', 'cuotas')->orderBy('id')->get()->map(fn ($v) => [
                    $v->id, $v->fecha_venta->toDateString(), $v->comprador->nombre, $v->comprador->ci, $v->vehiculo->chasis,
                    $v->tipo, $v->moneda, $v->precio, $v->inicial, $v->numero_cuotas, $v->saldo(),
                ])->all()),

            new HojaPlantilla('Cuotas', ['venta', 'numero', 'vencimiento', 'monto', 'pagado', 'saldo', 'estado'],
                Cuota::orderBy('venta_id')->orderBy('numero')->get()->map(fn ($c) => [
                    $c->venta_id, $c->numero, $c->vencimiento->toDateString(), $c->monto, $c->pagado, $c->saldo(), $c->nombreEstado(),
                ])->all()),

            new HojaPlantilla('Cobros', ['cobro', 'fecha', 'venta', 'medio', 'moneda_pago', 'monto_pago', 'tc', 'monto_aplicado', 'estado', 'motivo_anulacion'],
                Cobro::orderBy('id')->get()->map(fn ($c) => [
                    $c->id, $c->created_at->format('Y-m-d H:i'), $c->venta_id, $c->medio, $c->moneda_pago, $c->monto_pago,
                    $c->tc_aplicado, $c->monto_aplicado, $c->nombreEstado(), $c->motivo_anulacion,
                ])->all()),

            new HojaPlantilla('Repuestos', ['codigo', 'nombre', 'unidad', 'stock', 'stock_minimo', 'precio_bs'],
                Repuesto::orderBy('codigo')->get()->map(fn ($r) => [$r->codigo, $r->nombre, $r->unidad, $r->stock, $r->stock_minimo, $r->precio_bs])->all()),
        ];
    }
}
