<?php

/**
 * PBI-02 — CA-02: crear negocio y usuarios con rol; impedir usuarios duplicados
 * y altas hechas por quien no tiene permiso. RNF-04: aislamiento por negocio.
 */

use App\Models\User;
use App\Support\Roles;
use Livewire\Volt\Volt;

test('existen los 4 roles del Entregable con sus permisos', function () {
    $dueno = usuarioCon(Roles::DUENO);
    $cajero = usuarioCon(Roles::CAJERO);
    $vendedor = usuarioCon(Roles::VENDEDOR);
    $almacenero = usuarioCon(Roles::ALMACENERO);

    expect($dueno->can('usuarios.gestionar'))->toBeTrue()
        ->and($dueno->can('cobros.anular'))->toBeTrue()
        ->and($cajero->can('cobros.registrar'))->toBeTrue()
        ->and($cajero->can('cobros.anular'))->toBeFalse()
        ->and($cajero->can('vehiculos.gestionar'))->toBeFalse()
        ->and($vendedor->can('vehiculos.gestionar'))->toBeTrue()
        ->and($vendedor->can('cobros.registrar'))->toBeFalse()
        ->and($almacenero->can('repuestos.gestionar'))->toBeTrue()
        ->and($almacenero->can('usuarios.gestionar'))->toBeFalse();
});

test('el dueño crea un cajero en su propio negocio', function () {
    $dueno = usuarioCon(Roles::DUENO);

    Volt::actingAs($dueno)->test('usuarios.index')
        ->set('name', 'Rosa Mamani')
        ->set('email', 'rosa@ejemplo.com')
        ->set('rol', Roles::CAJERO)
        ->set('password', 'clave-segura-123')
        ->call('crear')
        ->assertHasNoErrors();

    $nuevo = User::where('email', 'rosa@ejemplo.com')->firstOrFail();

    expect($nuevo->negocio_id)->toBe($dueno->negocio_id)
        ->and($nuevo->hasRole(Roles::CAJERO))->toBeTrue();
});

test('no se puede crear un usuario con un correo ya registrado', function () {
    $dueno = usuarioCon(Roles::DUENO);
    usuarioCon(Roles::VENDEDOR)->update(['email' => 'repetido@ejemplo.com']);

    Volt::actingAs($dueno)->test('usuarios.index')
        ->set('name', 'Otra persona')
        ->set('email', 'repetido@ejemplo.com')
        ->set('rol', Roles::VENDEDOR)
        ->set('password', 'clave-segura-123')
        ->call('crear')
        ->assertHasErrors(['email' => 'unique']);

    expect(User::where('email', 'repetido@ejemplo.com')->count())->toBe(1);
});

test('quien no tiene permiso no puede dar de alta usuarios', function (string $rol) {
    $usuario = usuarioCon($rol);

    $this->actingAs($usuario)->get(route('usuarios.index'))->assertForbidden();
    Volt::actingAs($usuario)->test('usuarios.index')->assertForbidden();
})->with([Roles::CAJERO, Roles::VENDEDOR, Roles::ALMACENERO]);

test('el dueño solo ve a los usuarios de su negocio', function () {
    $dueno = usuarioCon(Roles::DUENO);
    usuarioCon(Roles::CAJERO, $dueno->negocio)->update(['name' => 'Cajero Propio']);
    usuarioCon(Roles::CAJERO)->update(['name' => 'Cajero Ajeno']);

    $this->actingAs($dueno)->get(route('usuarios.index'))
        ->assertOk()
        ->assertSee('Cajero Propio')
        ->assertDontSee('Cajero Ajeno');
});
