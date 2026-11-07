<?php

namespace App\Servicios;

use App\Models\Cobro;
use App\Models\MetricaUso;
use Carbon\CarbonImmutable;

/**
 * PBI-23 (CA-23): medición interna de uso según el protocolo del Entregable.
 *
 * - HP-03 (adopción): proporción de cobros registrados el mismo día en que se recibieron (meta: 80 %).
 * - HP-01 (valor): consultas de cartera por semana (meta: 3 o más).
 * - Tiempos: consulta de la cartera (meta: menos de un minuto en el 90 % de las consultas)
 *   y registro de un cobro (RNF: menos de 60 segundos).
 *
 * Se guardan solo conteos y tiempos, sin usuario ni datos del comprador. Cuando una semana no
 * tiene eventos, el indicador es null («sin dato»), que no es lo mismo que 0.
 */
final class Metricas
{
    public const META_MISMO_DIA = 80;      // %
    public const META_CONSULTAS = 3;       // por semana
    public const META_SEGUNDOS = 60;

    public function cobroRegistrado(Cobro $cobro, ?float $segundos): void
    {
        MetricaUso::create([
            'negocio_id' => $cobro->negocio_id,
            'evento' => 'cobro_registrado',
            'fecha' => $cobro->created_at->toDateString(),
            'segundos' => $segundos === null ? null : round(min($segundos, 999999), 2),
            'mismo_dia' => $cobro->registradoElMismoDia(),
        ]);
    }

    public function carteraConsultada(int $negocioId, float $segundos): void
    {
        MetricaUso::create([
            'negocio_id' => $negocioId,
            'evento' => 'cartera_consultada',
            'fecha' => today()->toDateString(),
            'segundos' => round($segundos, 2),
        ]);
    }

    /**
     * Indicadores de las últimas $semanas semanas (lunes a domingo), la más reciente primero.
     *
     * @return list<array{desde:CarbonImmutable, hasta:CarbonImmutable, cobros:int, mismo_dia:?float,
     *     consultas:int, consultas_rapidas:?float, mediana_cartera:?float, mediana_cobro:?float}>
     */
    public function semanas(int $semanas = 4): array
    {
        $inicio = CarbonImmutable::today()->startOfWeek()->subWeeks($semanas - 1);
        $eventos = MetricaUso::whereDate('fecha', '>=', $inicio)->get();
        $resultado = [];

        for ($i = $semanas - 1; $i >= 0; $i--) {
            $desde = $inicio->addWeeks($i);
            $hasta = $desde->endOfWeek();
            $semana = $eventos->filter(fn ($e) => $e->fecha->between($desde, $hasta));

            $cobros = $semana->where('evento', 'cobro_registrado');
            $consultas = $semana->where('evento', 'cartera_consultada');
            $tiemposCobro = $cobros->pluck('segundos')->filter(fn ($s) => $s !== null)->map(fn ($s) => (float) $s);
            $tiemposCartera = $consultas->pluck('segundos')->filter(fn ($s) => $s !== null)->map(fn ($s) => (float) $s);

            $resultado[] = [
                'desde' => $desde,
                'hasta' => $hasta,
                'cobros' => $cobros->count(),
                'mismo_dia' => $cobros->isEmpty() ? null : round(100 * $cobros->where('mismo_dia', true)->count() / $cobros->count(), 1),
                'consultas' => $consultas->count(),
                'consultas_rapidas' => $tiemposCartera->isEmpty() ? null
                    : round(100 * $tiemposCartera->filter(fn ($s) => $s < self::META_SEGUNDOS)->count() / $tiemposCartera->count(), 1),
                'mediana_cartera' => self::mediana($tiemposCartera->all()),
                'mediana_cobro' => self::mediana($tiemposCobro->all()),
            ];
        }

        return $resultado;
    }

    /** @param  list<float>  $valores */
    public static function mediana(array $valores): ?float
    {
        if ($valores === []) {
            return null;
        }
        sort($valores);
        $n = count($valores);
        $medio = intdiv($n, 2);

        return round($n % 2 ? $valores[$medio] : ($valores[$medio - 1] + $valores[$medio]) / 2, 2);
    }
}
