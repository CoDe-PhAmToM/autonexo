<?php

namespace App\Servicios;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * PBI-01 (spike) — todos los cálculos de dinero de AutoNexo pasan por aquí.
 *
 * Reglas (Entregable, sección 7.3):
 *  - Los montos entran y salen como texto con 2 decimales ("500.00"); por dentro se trabaja
 *    en centavos (enteros), nunca con float.
 *  - El tipo de cambio tiene 4 decimales ("12.2600").
 *  - Cada cuota se redondea a 2 decimales y la diferencia de redondeo va en la última.
 *  - Los pagos se aplican primero a la cuota más antigua; lo que sobra es excedente.
 */
final class CalculadoraDePlan
{
    /**
     * Genera el plan de cuotas de una venta a crédito.
     *
     * @return list<array{numero:int, vencimiento:CarbonImmutable, monto:string}>
     */
    public function generarCuotas(string $precio, string $inicial, int $numeroCuotas, CarbonInterface $primerVencimiento): array
    {
        $precioC = self::aCentavos($precio);
        $inicialC = self::aCentavos($inicial);

        if ($numeroCuotas < 1) {
            throw new InvalidArgumentException('El número de cuotas debe ser al menos 1.');
        }
        if ($precioC <= 0) {
            throw new InvalidArgumentException('El precio debe ser mayor a cero.');
        }
        if ($inicialC < 0 || $inicialC >= $precioC) {
            throw new InvalidArgumentException('La cuota inicial debe ser menor al precio.');
        }

        $saldoC = $precioC - $inicialC;
        $cuotaC = intdiv($saldoC, $numeroCuotas);          // redondeo a 2 decimales (hacia abajo)
        $ultimaC = $saldoC - $cuotaC * ($numeroCuotas - 1); // la diferencia va en la última

        if ($cuotaC < 100) {
            throw new InvalidArgumentException('Cada cuota debe ser de al menos 1,00.');
        }

        $inicio = CarbonImmutable::parse($primerVencimiento)->startOfDay();
        $cuotas = [];

        for ($n = 1; $n <= $numeroCuotas; $n++) {
            $cuotas[] = [
                'numero' => $n,
                'vencimiento' => $inicio->addMonthsNoOverflow($n - 1),
                'monto' => self::aTexto($n === $numeroCuotas ? $ultimaC : $cuotaC),
            ];
        }

        return $cuotas;
    }

    /** Convierte bolivianos a dólares con el tipo de cambio dado (redondeo comercial a 2 decimales). */
    public function bsAUsd(string $bolivianos, string $tipoCambio): string
    {
        $bsC = self::aCentavos($bolivianos);
        $tc = self::aDiezmilesimos($tipoCambio);

        return self::aTexto(self::dividirRedondeando($bsC * 10000, $tc));
    }

    /** Convierte dólares a bolivianos con el tipo de cambio dado (redondeo comercial a 2 decimales). */
    public function usdABs(string $dolares, string $tipoCambio): string
    {
        $usdC = self::aCentavos($dolares);
        $tc = self::aDiezmilesimos($tipoCambio);

        return self::aTexto(self::dividirRedondeando($usdC * $tc, 10000));
    }

    /**
     * Aplica un pago a las cuotas pendientes, de la más antigua a la más nueva.
     *
     * @param  array<int|string, string>  $saldos  saldo pendiente de cada cuota, ya ordenado (clave = id o número de cuota)
     * @return array{aplicado: array<int|string, string>, excedente: string}
     */
    public function aplicarPago(array $saldos, string $monto): array
    {
        $restanteC = self::aCentavos($monto);

        if ($restanteC <= 0) {
            throw new InvalidArgumentException('El monto del pago debe ser mayor a cero.');
        }

        $aplicado = [];

        foreach ($saldos as $cuota => $saldo) {
            if ($restanteC === 0) {
                break;
            }
            $saldoC = self::aCentavos($saldo);
            if ($saldoC <= 0) {
                continue;
            }
            $abonoC = min($saldoC, $restanteC);
            $aplicado[$cuota] = self::aTexto($abonoC);
            $restanteC -= $abonoC;
        }

        return ['aplicado' => $aplicado, 'excedente' => self::aTexto($restanteC)];
    }

    // ---------------------------------------------------------------- ayudas

    /** "9500" | "9500.5" | "9500.50" -> 950050 centavos. */
    public static function aCentavos(string|int $monto): int
    {
        $texto = trim((string) $monto);

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $texto, $m)) {
            throw new InvalidArgumentException("Monto inválido: «{$texto}». Use números con punto y hasta 2 decimales.");
        }

        $centavos = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return $m[1] === '-' ? -$centavos : $centavos;
    }

    /** 950050 -> "9500.50". */
    public static function aTexto(int $centavos): string
    {
        $signo = $centavos < 0 ? '-' : '';
        $abs = abs($centavos);

        return sprintf('%s%d.%02d', $signo, intdiv($abs, 100), $abs % 100);
    }

    /** "12.26" -> 122600 (tipo de cambio con 4 decimales). */
    public static function aDiezmilesimos(string $tipoCambio): int
    {
        $texto = trim($tipoCambio);

        if (! preg_match('/^(\d+)(?:\.(\d{1,4}))?$/', $texto, $m)) {
            throw new InvalidArgumentException("Tipo de cambio inválido: «{$texto}». Use hasta 4 decimales.");
        }

        $valor = (int) $m[1] * 10000 + (int) str_pad($m[2] ?? '0', 4, '0');

        if ($valor <= 0) {
            throw new InvalidArgumentException('El tipo de cambio debe ser mayor a cero.');
        }

        return $valor;
    }

    /** División entera con redondeo comercial (0,5 sube), para valores no negativos. */
    private static function dividirRedondeando(int $dividendo, int $divisor): int
    {
        return intdiv(2 * $dividendo + $divisor, 2 * $divisor);
    }
}
