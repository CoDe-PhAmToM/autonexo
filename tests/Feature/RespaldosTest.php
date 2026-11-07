<?php

/**
 * PBI-21 — CA-21: respaldo restaurado con los mismos conteos y saldos; respaldo fallido
 * visible; exportación solo autorizada.
 *
 * La restauración completa se prueba con `php artisan autonexo:probar-restauracion --generar`
 * (necesita crear una base aparte, por eso no corre dentro de la suite). Ver RESPALDO.md.
 */

use App\Models\Respaldo;
use App\Servicios\Respaldos;
use App\Support\Roles;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->dueno = usuarioCon(Roles::DUENO);
    $this->actingAs($this->dueno);
});

test('generar un respaldo deja el zip con el volcado y lo registra como exitoso', function () {
    Storage::fake('local');

    Volt::test('respaldos.index')->call('generar')->assertSee('Respaldo generado');

    $respaldo = Respaldo::sole();
    expect($respaldo->exitoso())->toBeTrue()
        ->and($respaldo->user_id)->toBe($this->dueno->id)
        ->and($respaldo->bytes)->toBeGreaterThan(0);

    $zip = app(Respaldos::class)->ultimoArchivo();
    $za = new ZipArchive;
    $za->open($zip);
    $nombres = collect(range(0, $za->numFiles - 1))->map(fn ($i) => str_replace('\\', '/', $za->getNameIndex($i)));
    $za->close();

    expect($nombres->contains(fn ($n) => str_starts_with($n, 'db-dumps/') && str_ends_with($n, '.sql')))->toBeTrue()
        ->and($nombres->contains(fn ($n) => str_ends_with($n, '.env')))->toBeFalse();
});

test('un respaldo fallido queda visible con su motivo', function () {
    Storage::fake('local');
    config(['database.connections.pgsql.dump.dump_binary_path' => 'C:/no/existe']);

    Volt::test('respaldos.index')->call('generar')->assertSee('El respaldo falló');

    expect(Respaldo::sole()->estado)->toBe('fallido');
    $this->get(route('respaldos.index'))->assertSee('El último respaldo falló')->assertSee('Fallido');
});

test('la exportación de registros solo la descarga el dueño', function () {
    $this->get(route('respaldos.exportar'))->assertOk()->assertDownload();

    foreach ([Roles::CAJERO, Roles::VENDEDOR, Roles::ALMACENERO] as $rol) {
        $this->actingAs(usuarioCon($rol, $this->dueno->negocio))->get(route('respaldos.exportar'))->assertForbidden();
    }
});

test('el resumen de verificación cuenta filas y saldos', function () {
    ventaDelEntregable();

    $resumen = app(Respaldos::class)->resumen();
    expect($resumen['Filas en cuotas'])->toBe('14')
        ->and($resumen['Saldo de cartera (cuotas)'])->toBe('7000.00');
});
