<?php

/**
 * PBI-06 — CA-06.1 a 06.3 (escenarios Gherkin del Entregable, sección 8.6).
 */

use App\Models\Venta;
use App\Models\Vehiculo;
use App\Servicios\Ventas;
use App\Support\Roles;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->vendedor = usuarioCon(Roles::VENDEDOR);
    $this->actingAs($this->vendedor);
    $this->vehiculo = Vehiculo::factory()->for($this->vendedor->negocio)->create(['precio_usd' => '9500.00']);
});

function formularioDeVenta($vehiculo, array $cambios = [])
{
    return Volt::test('ventas.crear', ['vehiculo' => $vehiculo])
        ->set(array_merge([
            'ci' => '6045123',
            'nombre' => 'Juan Carlos Mamani',
            'tipo' => 'credito',
            'precio' => '9500',
            'inicial' => '2500',
            'numero_cuotas' => '14',
            'monto_cuota' => '500',
            'primer_vencimiento' => '2026-12-05',
            'fecha_venta' => today()->toDateString(),
        ], $cambios))
        ->call('vender');
}

test('CA-06.1: vehículo reservado, USD 9.500 con 2.500 de inicial genera 14 cuotas de 500 y queda vendido', function () {
    app(Ventas::class)->reservar($this->vehiculo, ['ci' => '6045123', 'nombre' => 'Juan Carlos Mamani'], '500', 'USD', today()->addDays(3)->toDateString());
    expect($this->vehiculo->fresh()->estado)->toBe('reservado');

    formularioDeVenta($this->vehiculo)->assertHasNoErrors()->assertRedirect();

    $venta = Venta::with('cuotas')->sole();
    expect($venta->cuotas)->toHaveCount(14)
        ->and($venta->cuotas->pluck('monto')->unique()->all())->toBe(['500.00'])
        ->and($venta->cuotas->first()->vencimiento->toDateString())->toBe('2026-12-05')
        ->and($venta->cuotas->last()->vencimiento->toDateString())->toBe('2028-01-05')
        ->and($venta->saldo())->toBe('7000.00')
        ->and($venta->user_id)->toBe($this->vendedor->id)
        ->and($this->vehiculo->fresh()->estado)->toBe('vendido')
        ->and($venta->reserva_id)->not->toBeNull()
        ->and($this->vehiculo->reservas()->first()->estado)->toBe('convertida');
});

test('CA-06.2: si la inicial más las cuotas no iguala el precio, no se guarda y se muestra la diferencia', function () {
    $prueba = formularioDeVenta($this->vehiculo, ['monto_cuota' => '450']);

    $prueba->assertHasErrors('monto_cuota');
    expect($prueba->errors()->first('monto_cuota'))
        ->toContain('suman 8.800,00')
        ->toContain('faltan 700,00')
        ->toContain('500,00');

    expect(Venta::count())->toBe(0)
        ->and($this->vehiculo->fresh()->estado)->toBe('en_exhibicion');
});

test('CA-06.3: un vehículo vendido no se vuelve a vender y se indica su estado', function () {
    formularioDeVenta($this->vehiculo)->assertHasNoErrors();

    $otra = formularioDeVenta($this->vehiculo->fresh(), ['ci' => '7012456', 'nombre' => 'Otra persona']);
    $otra->assertHasErrors('vehiculo');
    expect($otra->errors()->first('vehiculo'))->toContain('Vendido');

    expect(Venta::count())->toBe(1);
});

test('la diferencia de redondeo va en la última cuota', function () {
    $this->vehiculo->update(['precio_usd' => '7000.00']);

    formularioDeVenta($this->vehiculo, ['precio' => '7000', 'inicial' => '0', 'numero_cuotas' => '3', 'monto_cuota' => '2333.33'])
        ->assertHasNoErrors();

    expect(Venta::sole()->cuotas->pluck('monto')->all())->toBe(['2333.33', '2333.33', '2333.34']);
});

test('el botón Calcular sugiere la cuota', function () {
    Volt::test('ventas.crear', ['vehiculo' => $this->vehiculo])
        ->set('inicial', '2500')
        ->set('numero_cuotas', '14')
        ->call('calcularCuota')
        ->assertSet('monto_cuota', '500.00');
});

test('venta al contado sin cuotas', function () {
    formularioDeVenta($this->vehiculo, ['tipo' => 'contado'])->assertHasNoErrors();

    $venta = Venta::sole();
    expect($venta->numero_cuotas)->toBe(0)
        ->and($venta->inicial)->toBe('9500.00')
        ->and($venta->cuotas()->count())->toBe(0);
});

test('venta en bolivianos exige el tipo de cambio pactado', function () {
    formularioDeVenta($this->vehiculo, ['moneda' => 'BOB', 'tc_pactado' => ''])->assertHasErrors('tc_pactado');

    formularioDeVenta($this->vehiculo, [
        'moneda' => 'BOB', 'tc_pactado' => '12.26', 'precio' => '116470', 'inicial' => '30650',
        'numero_cuotas' => '14', 'monto_cuota' => '6130',
    ])->assertHasNoErrors();

    $venta = Venta::sole();
    expect($venta->moneda)->toBe('BOB')
        ->and($venta->tc_pactado)->toBe('12.2600')
        ->and($venta->cuotas->first()->monto)->toBe('6130.00');
});

test('un vehículo reservado no se vende a otra persona', function () {
    app(Ventas::class)->reservar($this->vehiculo, ['ci' => '4987321', 'nombre' => 'Pedro Limachi'], '500', 'USD', today()->addDays(3)->toDateString());

    formularioDeVenta($this->vehiculo->fresh())->assertHasErrors('comprador.ci');
    expect(Venta::count())->toBe(0);
});

test('el cajero ve las ventas pero no puede vender', function () {
    $cajero = usuarioCon(Roles::CAJERO, $this->vendedor->negocio);
    formularioDeVenta($this->vehiculo)->assertHasNoErrors();

    $this->actingAs($cajero)->get(route('ventas.index'))->assertOk()->assertSee('Juan Carlos Mamani');
    $this->actingAs($cajero)->get(route('ventas.ver', Venta::sole()))->assertOk()->assertSee('Plan de cuotas');
    $this->actingAs($cajero)->get(route('ventas.crear', Vehiculo::factory()->for($cajero->negocio)->create()))->assertForbidden();
});

test('el almacenero no ve ventas', function () {
    $this->actingAs(usuarioCon(Roles::ALMACENERO))->get(route('ventas.index'))->assertForbidden();
});
