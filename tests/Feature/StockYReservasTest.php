<?php

/**
 * PBI-04 — CA-04: búsqueda por chasis, placa o modelo; el listado refleja el estado actual.
 * PBI-05 — CA-05: reserva con anticipo y fecha límite; una reserva vencida devuelve el vehículo
 * a «En exhibición»; no se puede reservar un vehículo reservado.
 */

use App\Models\Reserva;
use App\Models\Vehiculo;
use App\Servicios\Ventas;
use App\Support\Roles;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->vendedor = usuarioCon(Roles::VENDEDOR);
    $this->actingAs($this->vendedor);
    $negocio = $this->vendedor->negocio;

    $this->corolla = Vehiculo::factory()->for($negocio)->create(['marca' => 'Toyota', 'modelo' => 'Corolla', 'chasis' => 'JTDBR32E720111111', 'placa' => '2345ABC']);
    $this->hilux = Vehiculo::factory()->for($negocio)->create(['marca' => 'Toyota', 'modelo' => 'Hilux', 'chasis' => 'MR0FZ22G900222222', 'placa' => '4567XYZ', 'estado' => 'vendido']);
    $this->swift = Vehiculo::factory()->for($negocio)->create(['marca' => 'Suzuki', 'modelo' => 'Swift', 'chasis' => 'JS2ZC21S000333333', 'placa' => '1111DEF']);
});

// ---------------------------------------------------------------- PBI-04

test('CA-04: busca por chasis, placa o modelo', function (string $texto, string $esperado) {
    $prueba = Volt::test('vehiculos.index')->set('buscar', $texto);

    foreach (['Corolla', 'Hilux', 'Swift'] as $modelo) {
        $modelo === $esperado ? $prueba->assertSee($modelo) : $prueba->assertDontSee($modelo);
    }
})->with([
    'chasis parcial' => ['720111', 'Corolla'],
    'chasis con espacios y minúsculas' => ['mr0fz 22g9', 'Hilux'],
    'placa' => ['2345 abc', 'Corolla'],
    'modelo' => ['swift', 'Swift'],
]);

test('CA-04: filtra por estado y muestra el conteo de cada uno', function () {
    Volt::test('vehiculos.index')
        ->assertSee('En exhibición · 2')
        ->assertSee('Vendido · 1')
        ->set('estado', 'vendido')
        ->assertSee('Hilux')
        ->assertDontSee('Corolla');
});

// ---------------------------------------------------------------- PBI-05

test('CA-05: la reserva con anticipo y fecha límite deja el vehículo reservado', function () {
    Volt::test('vehiculos.reservar', ['vehiculo' => $this->corolla])
        ->set('ci', '4987321')
        ->set('nombre', 'Pedro Limachi')
        ->set('anticipo', '500')
        ->set('fecha_limite', today()->addDays(7)->toDateString())
        ->call('reservar')
        ->assertHasNoErrors();

    $reserva = Reserva::sole();
    expect($reserva->anticipo)->toBe('500.00')
        ->and($reserva->estado)->toBe('activa')
        ->and($reserva->fecha_limite->toDateString())->toBe(today()->addDays(7)->toDateString())
        ->and($this->corolla->fresh()->estado)->toBe('reservado');

    $this->get(route('vehiculos.ver', $this->corolla))->assertSee('Reservado para Pedro Limachi');
});

test('CA-05: no se puede reservar un vehículo reservado', function () {
    $ventas = app(Ventas::class);
    $ventas->reservar($this->corolla, ['ci' => '4987321', 'nombre' => 'Pedro Limachi'], '500', 'USD', today()->addDays(7)->toDateString());

    $segunda = Volt::test('vehiculos.reservar', ['vehiculo' => $this->corolla->fresh()])
        ->set('ci', '7012456')
        ->set('nombre', 'Otra persona')
        ->set('anticipo', '300')
        ->call('reservar');

    $segunda->assertHasErrors('vehiculo');
    expect($segunda->errors()->first('vehiculo'))->toContain('Reservado')
        ->and(Reserva::count())->toBe(1);
});

test('CA-05: una reserva vencida devuelve el vehículo a «En exhibición» al abrir el listado', function () {
    app(Ventas::class)->reservar($this->corolla, ['ci' => '4987321', 'nombre' => 'Pedro Limachi'], '500', 'USD', today()->addDays(2)->toDateString());

    $this->travel(3)->days();

    Volt::test('vehiculos.index')->set('estado', 'en_exhibicion')->assertSee('Corolla');

    expect($this->corolla->fresh()->estado)->toBe('en_exhibicion')
        ->and(Reserva::sole()->estado)->toBe('vencida');
});

test('la reserva sigue vigente hasta el final de su fecha límite', function () {
    app(Ventas::class)->reservar($this->corolla, ['ci' => '4987321', 'nombre' => 'Pedro Limachi'], '500', 'USD', today()->addDays(2)->toDateString());

    $this->travel(2)->days();
    app(Ventas::class)->vencerReservas();

    expect($this->corolla->fresh()->estado)->toBe('reservado');
});

test('cancelar la reserva libera el vehículo', function () {
    app(Ventas::class)->reservar($this->corolla, ['ci' => '4987321', 'nombre' => 'Pedro Limachi'], '500', 'USD', today()->addDays(7)->toDateString());

    Volt::test('vehiculos.ver', ['vehiculo' => $this->corolla->fresh()])->call('cancelarReserva');

    expect($this->corolla->fresh()->estado)->toBe('en_exhibicion')
        ->and(Reserva::sole()->estado)->toBe('cancelada');
});

test('un vehículo vendido no se puede reservar', function () {
    expect(fn () => app(Ventas::class)->reservar($this->hilux, ['ci' => '1', 'nombre' => 'X'], '100', 'USD', today()->toDateString()))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('el cajero no puede reservar', function () {
    $cajero = usuarioCon(Roles::CAJERO, $this->vendedor->negocio);
    $this->actingAs($cajero)->get(route('vehiculos.reservar', $this->corolla))->assertForbidden();
});
