<?php

namespace Database\Seeders;

use App\Models\MetricaUso;
use App\Models\Negocio;
use Illuminate\Database\Seeder;

/**
 * Métricas de uso de las 3 semanas anteriores (dato del caso), coherentes con el
 * Entregable, cap. 6: HP-03 con 71 % de cobros del mismo día al inicio y 84 % después
 * del recordatorio de cierre de caja; HP-01 con unas 4 a 5 consultas de cartera por semana.
 */
class MetricasDemoSeeder extends Seeder
{
    public function run(): void
    {
        $negocio = Negocio::where('nombre', 'Automotores Illimani (demo)')->first();
        if (! $negocio || MetricaUso::withoutGlobalScopes()->where('negocio_id', $negocio->id)->exists()) {
            return;
        }

        // [semanas atrás, cobros, cobros del mismo día, consultas de cartera]
        $semanas = [[3, 21, 15, 4], [2, 24, 17, 5], [1, 25, 21, 5]];
        mt_srand(2026);

        foreach ($semanas as [$atras, $cobros, $mismoDia, $consultas]) {
            $lunes = today()->startOfWeek()->subWeeks($atras);

            for ($i = 0; $i < $cobros; $i++) {
                MetricaUso::create([
                    'negocio_id' => $negocio->id,
                    'evento' => 'cobro_registrado',
                    'fecha' => $lunes->copy()->addDays($i % 6),
                    'segundos' => mt_rand(250, 750) / 10,   // 25 a 75 s
                    'mismo_dia' => $i < $mismoDia,
                ]);
            }

            for ($i = 0; $i < $consultas; $i++) {
                MetricaUso::create([
                    'negocio_id' => $negocio->id,
                    'evento' => 'cartera_consultada',
                    'fecha' => $lunes->copy()->addDays($i),
                    'segundos' => mt_rand(5, 40) / 100,     // 0,05 a 0,4 s
                ]);
            }
        }
    }
}
