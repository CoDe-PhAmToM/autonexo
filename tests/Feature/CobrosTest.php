<?php

/**
 * PBI-07 — CA-07.1 a 07.4 (escenarios Gherkin del Entregable, sección 8.6).
 */

use App\Exceptions\RegistroInmutable;
use App\Models\Bitacora;
use App\Models\Cobro;
use App\Models\TipoCambio;
use App\Servicios\Cobros;
use App\Support\Roles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->cajero = usuarioCon(Roles::CAJERO);
    $this->actingAs($this->cajero);
    TipoCambio::create(['fecha' => today(), 'valor' => '12.26', 'fuente' => 'Oficial']);
    $this->venta = ventaDelEntregable();
});

function cobrar($venta, array $datos)
{
    return Volt::test('ventas.cobrar', ['venta' => $venta])
        ->set(array_merge(['medio' => 'efectivo', 'moneda_pago' => 'BOB'], $datos))
        ->call('cobrar');
}

test('CA-07.1: Bs 6.130 al TC 12,26 pagan la cuota de USD 500 y se guarda todo el detalle', function () {
    cobrar($this->venta, ['monto' => '6130'])->assertHasNoErrors()->assertRedirect();

    $cobro = Cobro::sole();
    $cuota = $this->venta->cuotas()->first();

    expect($cuota->pagado)->toBe('500.00')
        ->and($cuota->estado())->toBe('pagada')
        ->and($cobro->monto_pago)->toBe('6130.00')
        ->and($cobro->moneda_pago)->toBe('BOB')
        ->and($cobro->tc_aplicado)->toBe('12.2600')
        ->and($cobro->fuente_tc)->toContain('Oficial')
        ->and($cobro->monto_aplicado)->toBe('500.00')
        ->and($cobro->user_id)->toBe($this->cajero->id)
        ->and($cobro->created_at->isToday())->toBeTrue()
        ->and($cobro->estado)->toBe('confirmado');
});

test('CA-07.2: Bs 3.065 dejan la cuota con saldo de USD 250 y en «Pago parcial»', function () {
    cobrar($this->venta, ['monto' => '3065'])->assertHasNoErrors();

    $cuota = $this->venta->cuotas()->first();
    expect($cuota->saldo())->toBe('250.00')
        ->and($cuota->nombreEstado())->toBe('Pago parcial');
});

test('CA-07.3: el pago por QR queda por confirmar y no cuenta hasta verificarlo', function () {
    Storage::fake('local');

    cobrar($this->venta, ['medio' => 'qr', 'monto' => '6130', 'comprobante' => UploadedFile::fake()->image('qr.jpg')])
        ->assertHasNoErrors();

    $cobro = Cobro::sole();
    expect($cobro->estado)->toBe('por_confirmar')
        ->and($this->venta->cuotas()->first()->pagado)->toBe('0.00')
        ->and($this->venta->fresh()->saldo())->toBe('7000.00');
    Storage::disk('local')->assertExists($cobro->comprobante_path);

    // El cajero no puede confirmar; el dueño sí.
    expect($this->cajero->can('cobros.confirmar'))->toBeFalse();
    $this->actingAs(usuarioCon(Roles::DUENO, $this->cajero->negocio));
    $cobro->confirmar();

    expect($this->venta->cuotas()->first()->pagado)->toBe('500.00')
        ->and($this->venta->fresh()->saldo())->toBe('6500.00')
        ->and($cobro->bitacora()->where('accion', 'confirmado')->exists())->toBeTrue();
});

test('el QR sin captura se rechaza', function () {
    cobrar($this->venta, ['medio' => 'qr', 'monto' => '6130'])->assertHasErrors('comprobante');
    expect(Cobro::count())->toBe(0);
});

test('un QR pendiente reserva su parte: el efectivo siguiente va a la próxima cuota', function () {
    Storage::fake('local');
    cobrar($this->venta, ['medio' => 'qr', 'monto' => '6130', 'comprobante' => UploadedFile::fake()->image('qr.jpg')]);
    cobrar($this->venta, ['monto' => '6130'])->assertHasNoErrors();

    $efectivo = Cobro::where('medio', 'efectivo')->sole();
    expect($efectivo->cuotas->pluck('numero')->all())->toBe([2]);
});

test('CA-07.4: anular un cobro no lo borra, queda en la bitácora con su autor y el saldo se recalcula', function () {
    cobrar($this->venta, ['monto' => '6130']);
    $cobro = Cobro::sole();
    expect($this->venta->cuotas()->first()->pagado)->toBe('500.00');

    $dueno = usuarioCon(Roles::DUENO, $this->cajero->negocio);
    $this->actingAs($dueno);

    Volt::test('ventas.ver', ['venta' => $this->venta])
        ->call('empezarAnulacion', $cobro->id)
        ->set('motivo', '')
        ->call('anular')
        ->assertHasErrors('motivo')
        ->set('motivo', 'Billete falso detectado en el arqueo')
        ->call('anular')
        ->assertHasNoErrors();

    $cobro->refresh();
    expect(Cobro::count())->toBe(1)
        ->and($cobro->estaAnulado())->toBeTrue()
        ->and($cobro->monto_pago)->toBe('6130.00')
        ->and($this->venta->cuotas()->first()->pagado)->toBe('0.00');

    $evento = Bitacora::where('auditable_type', 'cobro')->where('accion', 'anulado')->sole();
    expect($evento->user_id)->toBe($dueno->id)
        ->and($evento->motivo)->toBe('Billete falso detectado en el arqueo');
});

test('el cajero no puede anular cobros', function () {
    cobrar($this->venta, ['monto' => '6130']);

    Volt::test('ventas.ver', ['venta' => $this->venta])
        ->call('empezarAnulacion', Cobro::sole()->id)
        ->assertForbidden();
});

test('un cobro registrado no se edita ni se borra', function () {
    cobrar($this->venta, ['monto' => '6130']);
    $cobro = Cobro::sole();

    expect(fn () => $cobro->update(['monto_pago' => '1.00']))->toThrow(RegistroInmutable::class)
        ->and(fn () => $cobro->delete())->toThrow(RegistroInmutable::class);
});

test('cambiar el tipo de cambio oficial exige motivo y queda registrado (HP-02)', function () {
    cobrar($this->venta, ['monto' => '6150', 'tc' => '12.30'])->assertHasErrors('motivo_tc');

    cobrar($this->venta, ['monto' => '6150', 'tc' => '12.30', 'motivo_tc' => 'El comprador cambió en casa de cambio'])
        ->assertHasNoErrors();

    $cobro = Cobro::sole();
    expect($cobro->tc_aplicado)->toBe('12.3000')
        ->and($cobro->motivo_tc)->toBe('El comprador cambió en casa de cambio')
        ->and($cobro->monto_aplicado)->toBe('500.00');
});

test('un pago en la misma moneda de la venta no usa tipo de cambio', function () {
    cobrar($this->venta, ['moneda_pago' => 'USD', 'monto' => '1200'])->assertHasNoErrors();

    $cobro = Cobro::sole();
    expect($cobro->tc_aplicado)->toBeNull()
        ->and($cobro->cuotas->pluck('pivot.monto', 'numero')->all())->toBe([1 => '500.00', 2 => '500.00', 3 => '200.00']);
});

test('la regla de TC pactado usa el tipo de la venta', function () {
    $pactada = ventaDelEntregable(['regla_tc' => 'pactado', 'tc_pactado' => '10.00']);

    cobrar($pactada, ['monto' => '5000'])->assertHasNoErrors();
    expect(Cobro::sole()->monto_aplicado)->toBe('500.00')
        ->and(Cobro::sole()->fuente_tc)->toBe('Pactado en la venta');
});

test('un pago mayor al saldo pendiente se rechaza', function () {
    cobrar($this->venta, ['moneda_pago' => 'USD', 'monto' => '7000.01'])->assertHasErrors('monto');
    expect(Cobro::count())->toBe(0);
});

test('sin tipo de cambio registrado no se cobra en otra moneda', function () {
    TipoCambio::query()->delete();
    cobrar($this->venta, ['monto' => '6130'])->assertHasErrors('tc');
});

test('el tipo de cambio del día se registra una sola vez', function () {
    TipoCambio::query()->delete();

    Volt::test('cobros.index')->set('tc_valor', '12.26')->call('registrarTc')->assertHasNoErrors();
    Volt::test('cobros.index')->set('tc_valor', '12.40')->call('registrarTc')->assertHasErrors('tc_valor');

    expect(TipoCambio::sole()->valor)->toBe('12.2600');
});

test('el vendedor no puede cobrar', function () {
    $this->actingAs(usuarioCon(Roles::VENDEDOR, $this->cajero->negocio))
        ->get(route('ventas.cobrar', $this->venta))
        ->assertForbidden();
});

test('la simulación muestra a qué cuotas se aplica', function () {
    $sim = app(Cobros::class)->simular($this->venta, ['medio' => 'efectivo', 'moneda_pago' => 'BOB', 'monto' => '9195']);

    expect($sim['aplicado'])->toBe('750.00')
        ->and($sim['reparto'])->toBe([
            ['numero' => 1, 'monto' => '500.00', 'completa' => true],
            ['numero' => 2, 'monto' => '250.00', 'completa' => false],
        ]);
});
