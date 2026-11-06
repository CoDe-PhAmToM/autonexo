<?php

namespace App\Support;

/** Formato boliviano para mostrar montos: 9500.5 -> "9.500,50". Trabaja con texto, sin float. */
final class Formato
{
    public static function monto(string|int|null $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        [$entero, $decimales] = array_pad(explode('.', (string) $valor, 2), 2, '');
        $signo = str_starts_with($entero, '-') ? '-' : '';
        $entero = ltrim($entero, '-');

        return $signo.number_format((int) $entero, 0, ',', '.').','.str_pad(substr($decimales, 0, 2), 2, '0');
    }

    public static function usd(string|int|null $valor): string
    {
        return 'USD '.self::monto($valor);
    }

    public static function bs(string|int|null $valor): string
    {
        return 'Bs '.self::monto($valor);
    }
}
