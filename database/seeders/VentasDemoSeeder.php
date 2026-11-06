<?php

namespace Database\Seeders;

use App\Models\Repuesto;
use App\Models\Venta;
use App\Models\Vehiculo;
use App\Servicios\Ventas;
use Illuminate\Database\Seeder;

/**
 * Repuestos, una reserva y ventas a crédito de demostración.
 * Requiere un usuario autenticado del negocio demo (lo hace DatabaseSeeder).
 */
class VentasDemoSeeder extends Seeder
{
    public function run(Ventas $ventas): void
    {
        if (Repuesto::doesntExist()) {
            $repuestos = [
                ['FIL-ACE-TOY', 'Filtro de aceite Toyota', 24, 10, '45.00'],
                ['PAS-FRE-DEL', 'Pastillas de freno delanteras', 6, 8, '280.00'],
                ['BUJ-NGK-01', 'Bujía NGK', 40, 16, '35.00'],
                ['BAT-12V-65', 'Batería 12V 65 Ah', 1, 2, '890.00'],
                ['AMO-DEL-HIL', 'Amortiguador delantero Hilux', 4, 2, '620.00'],
            ];
            foreach ($repuestos as [$codigo, $nombre, $stock, $minimo, $precio]) {
                $r = Repuesto::create(['codigo' => $codigo, 'nombre' => $nombre, 'stock_minimo' => $minimo, 'precio_bs' => $precio]);
                $r->mover('entrada', $stock, 'Stock inicial');
            }
        }

        if (Venta::exists()) {
            return;
        }

        $disponibles = Vehiculo::where('estado', 'en_exhibicion')->orderBy('id')->take(3)->get();
        if ($disponibles->count() < 3) {
            return;
        }

        // Dos ventas a crédito de hace unos meses (tendrán cuotas vencidas para la cartera).
        foreach ([[$disponibles[0], 5, 'Juan Carlos Mamani', '6045123'], [$disponibles[1], 2, 'María Elena Quispe', '7012456']] as [$vehiculo, $meses, $nombre, $ci]) {
            $precio = (string) $vehiculo->precio_usd;
            $inicial = bcdiv(bcmul($precio, '0.3', 2), '1', 0).'.00';
            $plan = (new \App\Servicios\CalculadoraDePlan)->generarCuotas($precio, $inicial, 12, now());

            $ventas->vender($vehiculo, [
                'comprador' => ['ci' => $ci, 'nombre' => $nombre, 'telefono' => '7'.rand(1000000, 9999999)],
                'tipo' => 'credito',
                'moneda' => 'USD',
                'regla_tc' => 'oficial',
                'precio' => $precio,
                'inicial' => $inicial,
                'numero_cuotas' => 12,
                'monto_cuota' => $plan[0]['monto'],
                'primer_vencimiento' => today()->subMonthsNoOverflow($meses - 1)->toDateString(),
                'fecha_venta' => today()->subMonthsNoOverflow($meses)->toDateString(),
            ]);
        }

        // Una reserva activa.
        $ventas->reservar($disponibles[2], ['ci' => '4987321', 'nombre' => 'Pedro Limachi'], '500.00', 'USD', today()->addDays(5)->toDateString());
    }
}
