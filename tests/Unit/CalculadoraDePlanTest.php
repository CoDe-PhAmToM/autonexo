<?php

/**
 * PBI-01 — Spike de cálculo (CA-01): 300 casos con cero diferencias.
 *
 * Cada caso compara la CalculadoraDePlan (centavos en enteros) contra un cálculo
 * independiente hecho con bcmath sobre texto. Si los dos coinciden en los 300 casos,
 * el criterio CA-01 se cumple. Informe: php artisan test --filter=CalculadoraDePlan
 */

use App\Servicios\CalculadoraDePlan;
use Carbon\CarbonImmutable;

// ------------------------------------------------ escenarios del Entregable (Gherkin)

test('CA-06.1: USD 9.500 con USD 2.500 de inicial genera 14 cuotas de USD 500', function () {
    $cuotas = (new CalculadoraDePlan)->generarCuotas('9500', '2500', 14, CarbonImmutable::parse('2026-12-05'));

    expect($cuotas)->toHaveCount(14)
        ->and(array_unique(array_column($cuotas, 'monto')))->toBe(['500.00'])
        ->and($cuotas[0]['vencimiento']->toDateString())->toBe('2026-12-05')
        ->and($cuotas[13]['vencimiento']->toDateString())->toBe('2028-01-05');
});

test('CA-07.1: Bs 6.130 al tipo de cambio 12,26 pagan una cuota de USD 500', function () {
    $calc = new CalculadoraDePlan;
    $usd = $calc->bsAUsd('6130', '12.26');

    expect($usd)->toBe('500.00')
        ->and($calc->aplicarPago([1 => '500.00', 2 => '500.00'], $usd))
        ->toBe(['aplicado' => [1 => '500.00'], 'excedente' => '0.00']);
});

test('CA-07.2: Bs 3.065 dejan un saldo de USD 250 en la cuota', function () {
    $calc = new CalculadoraDePlan;
    $resultado = $calc->aplicarPago([1 => '500.00'], $calc->bsAUsd('3065', '12.26'));

    expect($resultado['aplicado'][1])->toBe('250.00')
        ->and(bcsub('500.00', $resultado['aplicado'][1], 2))->toBe('250.00');
});

test('la diferencia de redondeo va en la última cuota', function () {
    $cuotas = (new CalculadoraDePlan)->generarCuotas('7000', '0', 3, CarbonImmutable::parse('2026-12-31'));

    expect(array_column($cuotas, 'monto'))->toBe(['2333.33', '2333.33', '2333.34'])
        ->and($cuotas[1]['vencimiento']->toDateString())->toBe('2027-01-31')
        ->and($cuotas[2]['vencimiento']->toDateString())->toBe('2027-02-28'); // sin desbordar el mes
});

test('rechaza datos que no cuadran', function (string $precio, string $inicial, int $n) {
    (new CalculadoraDePlan)->generarCuotas($precio, $inicial, $n, CarbonImmutable::now());
})->throws(InvalidArgumentException::class)->with([
    'inicial igual al precio' => ['9500', '9500', 10],
    'cero cuotas' => ['9500', '2500', 0],
    'monto con 3 decimales' => ['9500.123', '0', 10],
    'monto con coma' => ['9500,50', '0', 10],
    'cuota menor a 1' => ['10', '9.50', 2],
]);

// ------------------------------------------------ los 300 casos del spike

dataset('casos_plan', function () {
    mt_srand(2026);
    for ($i = 1; $i <= 100; $i++) {
        $precio = mt_rand(300000, 6000000);                 // USD 3.000 a 60.000, en centavos
        $inicial = intdiv($precio * mt_rand(0, 40), 100);   // 0 % a 40 %
        yield sprintf('plan %03d', $i) => [
            CalculadoraDePlan::aTexto($precio), CalculadoraDePlan::aTexto($inicial), mt_rand(1, 36),
        ];
    }
});

dataset('casos_conversion', function () {
    mt_srand(1825);
    for ($i = 1; $i <= 100; $i++) {
        $monto = CalculadoraDePlan::aTexto(mt_rand(100, 10000000));                 // 1,00 a 100.000,00
        $tc = sprintf('%d.%04d', mt_rand(6, 13), mt_rand(0, 9999));                // 6,0000 a 13,9999
        yield sprintf('conversión %03d', $i) => [$monto, $tc];
    }
});

dataset('casos_pago', function () {
    mt_srand(500);
    for ($i = 1; $i <= 100; $i++) {
        $saldos = [];
        $total = 0;
        for ($c = 1, $n = mt_rand(1, 8); $c <= $n; $c++) {
            $s = mt_rand(0, 4) === 0 ? 0 : mt_rand(10000, 200000);              // algunas cuotas ya pagadas
            $saldos[$c] = CalculadoraDePlan::aTexto($s);
            $total += $s;
        }
        $monto = CalculadoraDePlan::aTexto(mt_rand(1, max(100, intdiv($total * 120, 100))));
        yield sprintf('pago %03d', $i) => [$saldos, $monto];
    }
});

test('spike: plan de cuotas', function (string $precio, string $inicial, int $n) {
    $cuotas = (new CalculadoraDePlan)->generarCuotas($precio, $inicial, $n, CarbonImmutable::parse('2026-12-05'));

    // Cálculo independiente con bcmath.
    $saldo = bcsub($precio, $inicial, 2);
    $cuota = bcdiv($saldo, (string) $n, 2);
    $ultima = bcsub($saldo, bcmul($cuota, (string) ($n - 1), 2), 2);
    $esperado = array_merge(array_fill(0, $n - 1, $cuota), [$ultima]);

    $suma = array_reduce(array_column($cuotas, 'monto'), fn ($t, $m) => bcadd($t, $m, 2), '0.00');

    expect(array_column($cuotas, 'monto'))->toBe($esperado)
        ->and($suma)->toBe($saldo)
        ->and(end($cuotas)['vencimiento']->toDateString())
        ->toBe(CarbonImmutable::parse('2026-12-05')->addMonthsNoOverflow($n - 1)->toDateString());
})->with('casos_plan');

test('spike: conversión Bs ⇄ USD', function (string $monto, string $tc) {
    $calc = new CalculadoraDePlan;

    // Redondeo comercial con bcmath: sumar 0,005 y truncar a 2 decimales.
    expect($calc->bsAUsd($monto, $tc))->toBe(bcadd(bcdiv($monto, $tc, 10), '0.005', 2))
        ->and($calc->usdABs($monto, $tc))->toBe(bcadd(bcmul($monto, $tc, 10), '0.005', 2));
})->with('casos_conversion');

test('spike: pago parcial y excedente', function (array $saldos, string $monto) {
    $resultado = (new CalculadoraDePlan)->aplicarPago($saldos, $monto);

    // Cálculo independiente: recorrer las cuotas de la más antigua a la más nueva.
    $restante = $monto;
    $esperado = [];
    foreach ($saldos as $cuota => $saldo) {
        if (bccomp($restante, '0', 2) === 0) {
            break;
        }
        if (bccomp($saldo, '0', 2) === 0) {
            continue;
        }
        $abono = bccomp($saldo, $restante, 2) <= 0 ? $saldo : $restante;
        $esperado[$cuota] = $abono;
        $restante = bcsub($restante, $abono, 2);
    }

    $totalAplicado = array_reduce($resultado['aplicado'], fn ($t, $m) => bcadd($t, $m, 2), '0.00');

    expect($resultado['aplicado'])->toBe($esperado)
        ->and($resultado['excedente'])->toBe($restante)
        ->and(bcadd($totalAplicado, $resultado['excedente'], 2))->toBe(bcadd($monto, '0', 2));
})->with('casos_pago');
