<?php

/**
 * PBI-08 — CA-08: la cartera suma los saldos pendientes confirmados y los clasifica por
 * tramos de antigüedad; los QR por confirmar se muestran aparte.
 * PBI-22 — CA-22: al cerrar la caja se comparan los cobros registrados del día con el
 * efectivo y los QR declarados; las diferencias quedan registradas.
 */

use App\Exceptions\RegistroInmutable;
use App\Models\CierreCaja;
use App\Models\Cobro;
use App\Models\TipoCambio;
use App\Servicios\Cartera;
use App\Servicios\Cobros;
use App\Support\Roles;
use Carbon\CarbonImmutable;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-06 10:00'));
    $this->cajero = usuarioCon(Roles::CAJERO);
    $this->actingAs($this->cajero);
    TipoCambio::create(['fecha' => today(), 'valor' => '12.26']);
});

// ---------------------------------------------------------------- PBI-08

test('CA-08: la cartera suma los saldos y los clasifica por tramos de antigüedad', function () {
    // 14 cuotas de 500: la primera venció el 1-jul-2026 (128 días antes del 6-nov).
    $venta = ventaDelEntregable(['primer_vencimiento' => '2026-07-01', 'fecha_venta' => '2026-06-01']);
    // Vencidas: 1-jul (128 d), 1-ago (97 d), 1-sep (66 d), 1-oct (36 d), 1-nov (5 d); el resto por vencer.
    app(Cobros::class)->registrar($venta, ['medio' => 'efectivo', 'moneda_pago' => 'USD', 'monto' => '700']); // paga la 1 y 200 de la 2

    $r = app(Cartera::class)->resumen($this->cajero->negocio_id)['USD'];

    expect($r['total'])->toBe('6300.00')
        ->and($r['tramos'])->toBe([
            'por_vencer' => '4500.00',   // 9 cuotas
            'd1_30' => '500.00',         // 1-nov
            'd31_60' => '500.00',        // 1-oct
            'd61_mas' => '800.00',       // 300 de la 2 (ago) + 500 de la 3 (sep)
        ])
        ->and($r['vencido'])->toBe('1800.00');
});

test('CA-08: un QR por confirmar no descuenta la cartera y se muestra aparte', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    $venta = ventaDelEntregable();
    app(Cobros::class)->registrar($venta, [
        'medio' => 'qr', 'moneda_pago' => 'USD', 'monto' => '500', 'comprobante_path' => 'comprobantes/x.jpg',
    ]);

    expect(app(Cartera::class)->resumen($this->cajero->negocio_id)['USD']['total'])->toBe('7000.00');

    $this->get(route('cartera.index'))
        ->assertOk()
        ->assertSee('7.000,00')
        ->assertSee('1 pago(s) por QR sin confirmar');
});

test('la cartera lista a los compradores en mora, del más atrasado al menos', function () {
    ventaDelEntregable(['primer_vencimiento' => '2026-10-20', 'comprador' => ['ci' => '111', 'nombre' => 'Atraso Corto']]);
    ventaDelEntregable(['primer_vencimiento' => '2026-08-01', 'comprador' => ['ci' => '222', 'nombre' => 'Atraso Largo']]);
    ventaDelEntregable(['primer_vencimiento' => '2026-12-01', 'comprador' => ['ci' => '333', 'nombre' => 'Al Dia']]);

    $mora = app(Cartera::class)->enMora($this->cajero->negocio_id);

    expect($mora->pluck('nombre')->all())->toBe(['Atraso Largo', 'Atraso Corto'])
        ->and((int) $mora[0]->dias_atraso)->toBe(97)
        ->and((int) $mora[0]->cuotas_vencidas)->toBe(4);
});

test('la cartera no mezcla negocios', function () {
    ventaDelEntregable();
    $otro = usuarioCon(Roles::DUENO);

    expect(app(Cartera::class)->resumen($otro->negocio_id))->toBe([]);
    $this->actingAs($otro)->get(route('cartera.index'))->assertOk()->assertSee('No hay saldos pendientes');
});

test('el vendedor no ve la cartera', function () {
    $this->actingAs(usuarioCon(Roles::VENDEDOR))->get(route('cartera.index'))->assertForbidden();
});

// ---------------------------------------------------------------- PBI-22

function cobrosDelDia($venta): void
{
    \Illuminate\Support\Facades\Storage::fake('local');
    $c = app(Cobros::class);
    $c->registrar($venta, ['medio' => 'efectivo', 'moneda_pago' => 'BOB', 'monto' => '6130']);
    $c->registrar($venta, ['medio' => 'efectivo', 'moneda_pago' => 'USD', 'monto' => '100']);
    $c->registrar($venta, ['medio' => 'qr', 'moneda_pago' => 'BOB', 'monto' => '1226', 'comprobante_path' => 'c/qr.jpg']);
    $anulado = $c->registrar($venta, ['medio' => 'efectivo', 'moneda_pago' => 'BOB', 'monto' => '500']);
    $anulado->anular('Registrado dos veces por error');
}

test('CA-22: el cierre compara lo registrado con lo declarado y guarda las diferencias', function () {
    cobrosDelDia(ventaDelEntregable());

    Volt::test('caja.index')
        ->assertSee('Bs 6.130,00')                 // efectivo Bs registrado (sin el anulado)
        ->set('declarado.efectivo_bob', '6100')    // faltan 30
        ->set('declarado.efectivo_usd', '100')
        ->set('declarado.qr_bob', '1226')
        ->set('declarado.qr_usd', '0')
        ->call('cerrar')
        ->assertHasErrors('observacion')           // hay diferencia: exige explicación
        ->set('observacion', 'Se dio cambio de más a un comprador')
        ->call('cerrar')
        ->assertHasNoErrors()
        ->assertSee('Caja cerrada con diferencias registradas');

    $cierre = CierreCaja::sole();
    expect($cierre->efectivo_bob_registrado)->toBe('6130.00')
        ->and($cierre->efectivo_bob_declarado)->toBe('6100.00')
        ->and($cierre->efectivo_bob_diferencia)->toBe('-30.00')
        ->and($cierre->efectivo_usd_diferencia)->toBe('0.00')
        ->and($cierre->qr_bob_registrado)->toBe('1226.00')
        ->and($cierre->cobros)->toBe(3)
        ->and($cierre->cuadra())->toBeFalse()
        ->and($cierre->observacion)->toBe('Se dio cambio de más a un comprador');
});

test('si todo cuadra no se exige observación', function () {
    cobrosDelDia(ventaDelEntregable());

    Volt::test('caja.index')
        ->set('declarado', ['efectivo_bob' => '6130', 'efectivo_usd' => '100', 'qr_bob' => '1226', 'qr_usd' => '0'])
        ->call('cerrar')
        ->assertHasNoErrors()
        ->assertSee('todo cuadra');

    expect(CierreCaja::sole()->cuadra())->toBeTrue();
});

test('la caja se cierra una vez por día y el cierre no se modifica', function () {
    $s = app(\App\Servicios\CierresDeCaja::class);
    $cierre = $s->cerrar($this->cajero, ['efectivo_bob' => '0', 'efectivo_usd' => '0', 'qr_bob' => '0', 'qr_usd' => '0']);

    expect(fn () => $s->cerrar($this->cajero, ['efectivo_bob' => '0', 'efectivo_usd' => '0', 'qr_bob' => '0', 'qr_usd' => '0']))
        ->toThrow(\Illuminate\Validation\ValidationException::class)
        ->and(fn () => $cierre->update(['observacion' => 'cambio']))->toThrow(RegistroInmutable::class);
});

test('cada cajero cierra solo sus propios cobros', function () {
    cobrosDelDia(ventaDelEntregable());
    $otroCajero = usuarioCon(Roles::CAJERO, $this->cajero->negocio);

    $registrado = app(\App\Servicios\CierresDeCaja::class)->registrado($otroCajero, today());
    expect($registrado['cobros'])->toBe(0)
        ->and($registrado['montos']['efectivo_bob'])->toBe('0.00');
});

test('el panel recuerda cerrar la caja si hubo cobros', function () {
    app(Cobros::class)->registrar(ventaDelEntregable(), ['medio' => 'efectivo', 'moneda_pago' => 'USD', 'monto' => '500']);

    $this->get(route('dashboard'))->assertSee('Todavía no cerraste la caja de hoy');

    app(\App\Servicios\CierresDeCaja::class)->cerrar($this->cajero, ['efectivo_bob' => '0', 'efectivo_usd' => '500', 'qr_bob' => '0', 'qr_usd' => '0']);
    $this->get(route('dashboard'))->assertDontSee('Todavía no cerraste la caja de hoy');
});

test('el vendedor no cierra caja', function () {
    $this->actingAs(usuarioCon(Roles::VENDEDOR))->get(route('caja.index'))->assertForbidden();
});
