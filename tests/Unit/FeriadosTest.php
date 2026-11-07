<?php

/** Feriados nacionales de Bolivia usados para el plazo del IMT (PBI-10). */

use App\Support\Feriados;
use Carbon\CarbonImmutable;

test('feriados nacionales de 2026', function () {
    expect(array_keys(Feriados::del(2026)))->toBe([
        '2026-01-01', // Año Nuevo
        '2026-01-22', // Estado Plurinacional
        '2026-02-16', // Carnaval (lunes)
        '2026-02-17', // Carnaval (martes)
        '2026-04-03', // Viernes Santo (Pascua: 5 de abril)
        '2026-05-01', // Trabajo
        '2026-06-04', // Corpus Christi
        '2026-06-22', // Año Nuevo Andino: el 21 cae domingo y pasa al lunes
        '2026-08-06', // Independencia
        '2026-11-02', // Difuntos
        '2026-12-25', // Navidad
    ]);
});

test('el plazo en días hábiles salta fines de semana y feriados', function () {
    // Viernes 31-jul-2026 + 10 días hábiles: salta el jueves 6 de agosto.
    expect(Feriados::sumarDiasHabiles(CarbonImmutable::parse('2026-07-31'), 10)->toDateString())->toBe('2026-08-17')
        // Sin feriados en el medio: viernes 6-nov + 10 = viernes 20-nov.
        ->and(Feriados::sumarDiasHabiles(CarbonImmutable::parse('2026-11-06'), 10)->toDateString())->toBe('2026-11-20')
        // Carnaval: viernes 13-feb + 1 día hábil = miércoles 18-feb.
        ->and(Feriados::sumarDiasHabiles(CarbonImmutable::parse('2026-02-13'), 1)->toDateString())->toBe('2026-02-18');
});

test('cuenta los días hábiles entre dos fechas', function () {
    expect(Feriados::diasHabilesEntre(CarbonImmutable::parse('2026-08-03'), CarbonImmutable::parse('2026-08-10')))->toBe(4);
});
