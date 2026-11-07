<?php

use App\Servicios\Respaldos;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 * PBI-21: respaldo diario (requiere el programador de Laravel: php artisan schedule:work).
 * También se puede generar a mano desde la pantalla Respaldos.
 */
Schedule::command('backup:run --disable-notifications')->dailyAt('23:30');
Schedule::command('backup:clean --disable-notifications')->dailyAt('23:45');

/*
 * PBI-21 (CA-21): prueba la restauración del último respaldo en una base limpia
 * y compara conteos y saldos con la base actual.
 *
 *   php artisan autonexo:probar-restauracion
 */
Artisan::command('autonexo:probar-restauracion {--generar : Genera un respaldo nuevo antes de probar} {--conservar : No borra la base restaurada}', function (Respaldos $respaldos) {
    if ($this->option('generar')) {
        $this->info('Generando respaldo…');
        $r = $respaldos->generar();
        if (! $r->exitoso()) {
            $this->error('El respaldo falló: '.$r->mensaje);

            return 1;
        }
        $this->line("Respaldo listo: {$r->archivo} ({$r->tamano()})");
    }

    $this->info('Restaurando el último respaldo en la base '.Respaldos::BASE_DE_PRUEBA.'…');
    $resultado = $respaldos->probarRestauracion((bool) $this->option('conservar'));

    $this->table(
        ['Dato', 'Base actual', 'Base restaurada', ''],
        collect($resultado['original'])->map(fn ($valor, $dato) => [
            $dato, $valor, $resultado['restaurada'][$dato] ?? '—', $valor === ($resultado['restaurada'][$dato] ?? null) ? 'OK' : 'DIFERENTE',
        ])->values()->all(),
    );

    if ($resultado['coincide']) {
        $this->info("Restauración verificada: {$resultado['archivo']} reproduce los mismos conteos y saldos.");

        return 0;
    }

    $this->error('La base restaurada no coincide con la actual.');

    return 1;
})->purpose('Prueba que el último respaldo se restaura con los mismos conteos y saldos');
