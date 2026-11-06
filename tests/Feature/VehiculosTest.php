<?php

/**
 * PBI-03 — CA-03: el chasis duplicado en el negocio se detecta;
 * corregir la placa conserva el historial del vehículo.
 */

use App\Models\Bitacora;
use App\Models\Vehiculo;
use App\Support\Roles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

function registrarVehiculo($usuario, array $datos = [])
{
    return Volt::actingAs($usuario)->test('vehiculos.formulario')
        ->set(array_merge([
            'chasis' => 'jtdbr32e 720123456',
            'placa' => '2345 abc',
            'marca' => 'Toyota',
            'modelo' => 'Corolla',
            'anio' => '2018',
            'costo_usd' => '8500',
            'precio_usd' => '9500.00',
        ], $datos))
        ->call('guardar');
}

test('el vendedor registra un vehículo por chasis y queda en la bitácora', function () {
    $vendedor = usuarioCon(Roles::VENDEDOR);

    registrarVehiculo($vendedor)->assertHasNoErrors();

    $vehiculo = Vehiculo::withoutGlobalScopes()->sole();

    expect($vehiculo->chasis)->toBe('JTDBR32E720123456')
        ->and($vehiculo->placa)->toBe('2345ABC')
        ->and($vehiculo->negocio_id)->toBe($vendedor->negocio_id)
        ->and($vehiculo->precio_usd)->toBe('9500.00')
        ->and($vehiculo->estado)->toBe('en_exhibicion');

    $evento = Bitacora::withoutGlobalScopes()->sole();
    expect($evento->accion)->toBe('creado')
        ->and($evento->user_id)->toBe($vendedor->id);
});

test('el chasis duplicado en el mismo negocio se detecta', function () {
    $vendedor = usuarioCon(Roles::VENDEDOR);
    registrarVehiculo($vendedor)->assertHasNoErrors();

    // Mismo chasis escrito distinto (minúsculas, guiones): igual se detecta.
    registrarVehiculo($vendedor, ['chasis' => 'JTDBR32E-720123456', 'placa' => '9999XYZ'])
        ->assertHasErrors(['chasis' => 'unique']);

    expect(Vehiculo::withoutGlobalScopes()->count())->toBe(1);
});

test('el mismo chasis sí se permite en otro negocio', function () {
    registrarVehiculo(usuarioCon(Roles::VENDEDOR))->assertHasNoErrors();
    registrarVehiculo(usuarioCon(Roles::VENDEDOR))->assertHasNoErrors();

    expect(Vehiculo::withoutGlobalScopes()->count())->toBe(2);
});

test('corregir la placa conserva el vehículo y su historial', function () {
    $vendedor = usuarioCon(Roles::VENDEDOR);
    $this->actingAs($vendedor);
    $vehiculo = Vehiculo::factory()->for($vendedor->negocio)->create(['placa' => '2345ABC']);

    Volt::test('vehiculos.formulario', ['vehiculo' => $vehiculo])
        ->set('placa', '2345ABD')
        ->call('guardar')
        ->assertHasNoErrors();

    expect(Vehiculo::count())->toBe(1)
        ->and($vehiculo->fresh()->placa)->toBe('2345ABD');

    $correccion = $vehiculo->bitacora()->where('accion', 'actualizado')->sole();
    expect($correccion->cambios)->toBe(['placa' => ['antes' => '2345ABC', 'despues' => '2345ABD']])
        ->and($correccion->user_id)->toBe($vendedor->id);

    $this->get(route('vehiculos.ver', $vehiculo))
        ->assertOk()
        ->assertSee('2345ABC')
        ->assertSee('2345ABD');
});

test('la foto se guarda y solo se ve desde el negocio dueño', function () {
    Storage::fake('local');
    $vendedor = usuarioCon(Roles::VENDEDOR);

    registrarVehiculo($vendedor, ['foto' => UploadedFile::fake()->image('auto.jpg')])->assertHasNoErrors();
    $vehiculo = Vehiculo::withoutGlobalScopes()->sole();

    Storage::disk('local')->assertExists($vehiculo->foto_path);
    $this->actingAs($vendedor)->get(route('vehiculos.foto', $vehiculo))->assertOk();
    $this->actingAs(usuarioCon(Roles::DUENO))->get(route('vehiculos.foto', $vehiculo))->assertNotFound();
});

test('un usuario de otro negocio no ve vehículos ajenos', function () {
    $propio = usuarioCon(Roles::VENDEDOR);
    $ajeno = usuarioCon(Roles::DUENO);
    $vehiculo = Vehiculo::factory()->for($propio->negocio)->create(['modelo' => 'ModeloPropio']);

    $this->actingAs($ajeno)->get(route('vehiculos.index'))->assertOk()->assertDontSee('ModeloPropio');
    $this->actingAs($ajeno)->get(route('vehiculos.ver', $vehiculo))->assertNotFound();
    $this->actingAs($ajeno)->get(route('vehiculos.editar', $vehiculo))->assertNotFound();
});

test('cajero y almacenero ven el inventario pero no lo modifican', function (string $rol) {
    $usuario = usuarioCon($rol);

    $this->actingAs($usuario)->get(route('vehiculos.index'))->assertOk()->assertDontSee('Nuevo vehículo');
    $this->actingAs($usuario)->get(route('vehiculos.crear'))->assertForbidden();
})->with([Roles::CAJERO, Roles::ALMACENERO]);
