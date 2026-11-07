<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Feriados nacionales de Bolivia, para contar días hábiles (plazo del IMT, PBI-10).
 *
 * - Fijos: 1 de enero, 22 de enero (Estado Plurinacional), 1 de mayo, 21 de junio
 *   (Año Nuevo Andino Amazónico), 6 de agosto, 2 de noviembre y 25 de diciembre.
 *   Si caen en domingo, se recorren al lunes.
 * - Móviles (según la Pascua): lunes y martes de Carnaval, Viernes Santo y Corpus Christi.
 *
 * No incluye los feriados departamentales.
 */
final class Feriados
{
    private const FIJOS = [
        '01-01' => 'Año Nuevo',
        '01-22' => 'Día del Estado Plurinacional',
        '05-01' => 'Día del Trabajo',
        '06-21' => 'Año Nuevo Andino Amazónico',
        '08-06' => 'Día de la Independencia',
        '11-02' => 'Día de los Difuntos',
        '12-25' => 'Navidad',
    ];

    /** @var array<int, array<string, string>> */
    private static array $cache = [];

    /** @return array<string, string> fecha Y-m-d => nombre */
    public static function del(int $anio): array
    {
        if (isset(self::$cache[$anio])) {
            return self::$cache[$anio];
        }

        $feriados = [];
        foreach (self::FIJOS as $mesDia => $nombre) {
            $fecha = CarbonImmutable::parse("{$anio}-{$mesDia}");
            if ($fecha->isSunday()) {
                $fecha = $fecha->addDay();
            }
            $feriados[$fecha->toDateString()] = $nombre;
        }

        // Pascua: 21 de marzo + easter_days() (extensión calendar de PHP).
        $pascua = CarbonImmutable::create($anio, 3, 21)->addDays(easter_days($anio));
        $feriados[$pascua->subDays(48)->toDateString()] = 'Carnaval (lunes)';
        $feriados[$pascua->subDays(47)->toDateString()] = 'Carnaval (martes)';
        $feriados[$pascua->subDays(2)->toDateString()] = 'Viernes Santo';
        $feriados[$pascua->addDays(60)->toDateString()] = 'Corpus Christi';

        ksort($feriados);

        return self::$cache[$anio] = $feriados;
    }

    public static function esFeriado(CarbonInterface $fecha): bool
    {
        return isset(self::del($fecha->year)[$fecha->toDateString()]);
    }

    public static function esHabil(CarbonInterface $fecha): bool
    {
        return ! $fecha->isWeekend() && ! self::esFeriado($fecha);
    }

    /** Suma días hábiles (lunes a viernes que no son feriado). */
    public static function sumarDiasHabiles(CarbonInterface $desde, int $dias): CarbonImmutable
    {
        $fecha = CarbonImmutable::parse($desde)->startOfDay();
        while ($dias > 0) {
            $fecha = $fecha->addDay();
            if (self::esHabil($fecha)) {
                $dias--;
            }
        }

        return $fecha;
    }

    /** Días hábiles desde $desde (sin contarlo) hasta $hasta (contándolo). */
    public static function diasHabilesEntre(CarbonInterface $desde, CarbonInterface $hasta): int
    {
        $fecha = CarbonImmutable::parse($desde)->startOfDay();
        $fin = CarbonImmutable::parse($hasta)->startOfDay();
        $dias = 0;
        while ($fecha->lt($fin)) {
            $fecha = $fecha->addDay();
            if (self::esHabil($fecha)) {
                $dias++;
            }
        }

        return $dias;
    }
}
