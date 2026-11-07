<?php

/**
 * PBI-09 — CA-09: al día siguiente del vencimiento la cuota impaga aparece en la lista de
 * alertas; una cuota pagada no genera alerta.
 * PBI-12 — CA-12: comprobante ligado al cobro y estado de cuenta actualizado; sin
 * consentimiento registrado no se envía; no sustituye una factura.
 * PBI-23 — CA-23: conteos y tiempos según el protocolo; distinguir «sin dato» de cero;
 * no recoger datos personales innecesarios.
 */

use App\Models\Bitacora;
use App\Models\Cobro;
use App\Models\MetricaUso;
use App\Models\TipoCambio;
use App\Servicios\Cobros;
use App\Servicios\Comprobantes;
use App\Servicios\Metricas;
use App\Support\Roles;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-06 10:00'));
    $this->dueno = usuarioCon(Roles::DUENO);
    $this->actingAs($this->dueno);
    TipoCambio::create(['fecha' => today(), 'valor' => '12.26']);
});

// ---------------------------------------------------------------- PBI-09

test('CA-09: la cuota impaga aparece en las alertas al día siguiente de su vencimiento', function () {
    ventaDelEntregable(['primer_vencimiento' => '2026-11-06', 'comprador' => ['ci' => '1', 'nombre' => 'Vence Hoy']]);
    ventaDelEntregable(['primer_vencimiento' => '2026-11-05', 'comprador' => ['ci' => '2', 'nombre' => 'Vencio Ayer']]);

    $this->get(route('dashboard'))
        ->assertSee('Cuotas vencidas (1)')
        ->assertSee('Vencio Ayer')
        ->assertDontSee('Vence Hoy');

    $this->travelTo(CarbonImmutable::parse('2026-11-07 08:00'));
    $this->get(route('dashboard'))->assertSee('Cuotas vencidas (2)')->assertSee('Vence Hoy');
});

test('CA-09: una cuota pagada no genera alerta', function () {
    $venta = ventaDelEntregable(['primer_vencimiento' => '2026-11-01', 'comprador' => ['ci' => '3', 'nombre' => 'Pago Puntual']]);
    app(Cobros::class)->registrar($venta, ['medio' => 'efectivo', 'moneda_pago' => 'USD', 'monto' => '500']);

    $this->get(route('dashboard'))->assertDontSee('Cuotas vencidas (')->assertDontSee('Pago Puntual');
});

test('el panel muestra el total por cobrar y lo vencido', function () {
    ventaDelEntregable(['primer_vencimiento' => '2026-10-01']); // vencidas: 1-oct y 1-nov

    $this->get(route('dashboard'))
        ->assertSee('USD 7.000,00')
        ->assertSee('Vencido: USD 1.000,00');
});

test('el vendedor no ve la cartera en el panel', function () {
    ventaDelEntregable(['primer_vencimiento' => '2026-10-01']);

    $this->actingAs(usuarioCon(Roles::VENDEDOR, $this->dueno->negocio))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Cuotas vencidas');
});

// ---------------------------------------------------------------- PBI-12

test('CA-12: sin consentimiento registrado no se genera el comprobante ni el estado de cuenta', function () {
    $venta = ventaDelEntregable();
    $cobro = app(Cobros::class)->registrar($venta, ['medio' => 'efectivo', 'moneda_pago' => 'BOB', 'monto' => '6130']);

    $this->get(route('cobros.pdf', $cobro))->assertForbidden();
    $this->get(route('ventas.estado-cuenta', $venta))->assertForbidden();
    expect(app(Comprobantes::class)->enlaceWhatsApp($cobro))->toBeNull();
});

test('CA-12: con consentimiento se genera el comprobante ligado al cobro y el estado de cuenta actualizado', function () {
    $venta = ventaDelEntregable(['comprador' => ['ci' => '6045123', 'nombre' => 'Juan Carlos Mamani', 'telefono' => '71234567']]);
    $cobro = app(Cobros::class)->registrar($venta, ['medio' => 'efectivo', 'moneda_pago' => 'BOB', 'monto' => '6130']);

    Volt::test('ventas.ver', ['venta' => $venta])->call('cambiarConsentimiento', true);
    expect($venta->comprador->fresh()->consentimiento_comprobante)->toBeTrue()
        ->and(Bitacora::where('auditable_type', 'comprador')->where('accion', 'actualizado')->sole()->user_id)->toBe($this->dueno->id);

    $this->get(route('cobros.pdf', $cobro))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('ventas.estado-cuenta', $venta))->assertOk()->assertHeader('content-type', 'application/pdf');

    // Contenido (se revisa el HTML que se convierte a PDF).
    $cobro->load('venta.comprador', 'venta.vehiculo', 'venta.negocio', 'venta.cuotas', 'cuotas', 'user');
    $html = view('pdf.comprobante', ['cobro' => $cobro])->render();
    expect($html)->toContain('Comprobante de pago N.º '.$cobro->id)
        ->toContain('Bs 6.130,00')
        ->toContain('USD 500,00')
        ->toContain('Saldo pendiente de la venta: <span class="total">USD 6.500,00')
        ->toContain('No sustituye a una factura');

    $wa = app(Comprobantes::class)->enlaceWhatsApp($cobro->fresh());
    expect($wa)->toStartWith('https://wa.me/59171234567?text=')
        ->and(rawurldecode($wa))->toContain('no sustituye a una factura');
});

test('el comprador descarga el PDF con el enlace firmado, sin iniciar sesión', function () {
    $venta = ventaDelEntregable(['comprador' => ['ci' => '6045123', 'nombre' => 'Juan Carlos Mamani', 'telefono' => '71234567']]);
    $venta->comprador->registrarConsentimiento(true);
    $cobro = app(Cobros::class)->registrar($venta, ['medio' => 'efectivo', 'moneda_pago' => 'USD', 'monto' => '500']);
    $otro = app(Cobros::class)->registrar($venta, ['medio' => 'efectivo', 'moneda_pago' => 'USD', 'monto' => '500']);

    $enlace = app(Comprobantes::class)->enlacePublico($cobro);
    expect(rawurldecode(app(Comprobantes::class)->enlaceWhatsApp($cobro->fresh())))->toContain('/comprobante/'.$cobro->id.'?');

    auth()->logout();
    $this->get($enlace)->assertOk()->assertHeader('content-type', 'application/pdf');

    // Alterado para ver otro cobro: rechazado.
    $this->get(str_replace('/comprobante/'.$cobro->id.'?', '/comprobante/'.$otro->id.'?', $enlace))->assertForbidden();

    // Vencido (más de 7 días): rechazado.
    $this->travel(8)->days();
    $this->get($enlace)->assertForbidden();
});

test('el enlace firmado tampoco sirve si el comprador retiró su consentimiento', function () {
    $venta = ventaDelEntregable();
    $venta->comprador->registrarConsentimiento(true);
    $cobro = app(Cobros::class)->registrar($venta, ['medio' => 'efectivo', 'moneda_pago' => 'USD', 'monto' => '500']);
    $enlace = app(Comprobantes::class)->enlacePublico($cobro);
    $venta->comprador->registrarConsentimiento(false);

    auth()->logout();
    $this->get($enlace)->assertForbidden();
});

test('el comprobante de un cobro anulado lo indica', function () {
    $venta = ventaDelEntregable();
    $venta->comprador->registrarConsentimiento(true);
    $cobro = app(Cobros::class)->registrar($venta, ['medio' => 'efectivo', 'moneda_pago' => 'USD', 'monto' => '500']);
    $cobro->anular('Error de digitación en el monto');

    $cobro = $cobro->fresh(['venta.comprador', 'venta.vehiculo', 'venta.negocio', 'venta.cuotas', 'cuotas', 'user']);
    expect(view('pdf.comprobante', ['cobro' => $cobro])->render())->toContain('ANULADO')->toContain('Error de digitación');
});

// ---------------------------------------------------------------- PBI-23

test('CA-23: registrar un cobro desde la pantalla mide el tiempo y si fue el mismo día', function () {
    $venta = ventaDelEntregable();

    Volt::test('ventas.cobrar', ['venta' => $venta])
        ->set('moneda_pago', 'USD')->set('monto', '500')
        ->call('cobrar')->assertHasNoErrors();

    Volt::test('ventas.cobrar', ['venta' => $venta])
        ->set('moneda_pago', 'USD')->set('monto', '500')->set('fecha_pago', '2026-11-04') // pago de otro día
        ->call('cobrar')->assertHasNoErrors();

    $metricas = MetricaUso::where('evento', 'cobro_registrado')->orderBy('id')->get();
    expect($metricas)->toHaveCount(2)
        ->and($metricas[0]->mismo_dia)->toBeTrue()
        ->and($metricas[1]->mismo_dia)->toBeFalse()
        ->and($metricas[0]->segundos)->not->toBeNull()
        ->and(Cobro::orderBy('id')->get()->last()->fecha_pago->toDateString())->toBe('2026-11-04');

    $semana = app(Metricas::class)->semanas(1)[0];
    expect($semana['cobros'])->toBe(2)
        ->and($semana['mismo_dia'])->toBe(50.0);
});

test('CA-23: cada consulta de cartera se cuenta con su tiempo', function () {
    $this->get(route('cartera.index'))->assertOk();
    $this->get(route('cartera.index'))->assertOk();

    $semana = app(Metricas::class)->semanas(1)[0];
    expect($semana['consultas'])->toBe(2)
        ->and($semana['consultas_rapidas'])->toBe(100.0)
        ->and($semana['mediana_cartera'])->not->toBeNull();
});

test('CA-23: una semana sin eventos es «sin dato», no cero', function () {
    $semanas = app(Metricas::class)->semanas(4);

    expect($semanas[0]['mismo_dia'])->toBeNull()
        ->and($semanas[0]['mediana_cobro'])->toBeNull()
        ->and($semanas[0]['consultas'])->toBe(0);

    $this->get(route('metricas.index'))->assertOk()->assertSee('Sin dato');
});

test('CA-23: las métricas no guardan datos personales', function () {
    $columnas = Schema::getColumnListing('metricas_uso');

    expect($columnas)->not->toContain('user_id')
        ->not->toContain('comprador_id')
        ->not->toContain('ci')
        ->not->toContain('nombre');
});

test('solo el dueño ve la medición de uso', function () {
    $this->actingAs(usuarioCon(Roles::CAJERO, $this->dueno->negocio))->get(route('metricas.index'))->assertForbidden();
});

test('un pago con fecha futura se rechaza', function () {
    Volt::test('ventas.cobrar', ['venta' => ventaDelEntregable()])
        ->set('moneda_pago', 'USD')->set('monto', '500')->set('fecha_pago', '2026-11-07')
        ->call('cobrar')->assertHasErrors('fecha_pago');
});
