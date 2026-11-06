<?php

/**
 * PBI-10 — CA-10.1 a 10.3 (escenarios Gherkin del Entregable, sección 8.6).
 */

use App\Models\Transferencia;
use App\Servicios\Transferencias;
use App\Support\Roles;
use Carbon\CarbonImmutable;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-06 10:00')); // viernes
    $this->dueno = usuarioCon(Roles::DUENO);
    $this->actingAs($this->dueno);
    $this->venta = ventaDelEntregable();
});

test('CA-10.1: al registrar la entrega se crea la lista con sus pasos y el límite del IMT en días hábiles', function () {
    Volt::test('ventas.ver', ['venta' => $this->venta])
        ->set('fecha_minuta', '2026-11-06')
        ->call('registrarEntrega')
        ->assertHasNoErrors();

    $t = Transferencia::with('pasos')->sole();
    expect($t->pasos->pluck('nombre')->all())->toBe(array_values(Transferencia::PASOS))
        // viernes 6 + 10 días hábiles = viernes 20 de noviembre (salta 2 fines de semana)
        ->and($t->fecha_limite_imt->toDateString())->toBe('2026-11-20')
        ->and($t->pasos->firstWhere('clave', 'minuta')->cumplido())->toBeTrue()
        ->and($t->estado)->toBe('en_curso')
        ->and($this->venta->vehiculo->fresh()->estado)->toBe('vendido');
});

test('CA-10.2: con el IMT pendiente y a 2 días hábiles del límite, el dueño ve la alerta', function () {
    $t = app(Transferencias::class)->iniciar($this->venta, '2026-11-06');

    $this->travelTo(CarbonImmutable::parse('2026-11-17')); // martes: faltan 3 días hábiles
    expect($t->fresh('pasos')->alertaImt())->toBeFalse();
    $this->get(route('dashboard'))->assertDontSee('IMT:');

    $this->travelTo(CarbonImmutable::parse('2026-11-18')); // miércoles: faltan 2
    expect($t->fresh('pasos')->alertaImt())->toBeTrue();
    $this->get(route('dashboard'))
        ->assertSee('IMT: faltan 2 días hábiles')
        ->assertSee($this->venta->vehiculo->descripcion())
        ->assertSee('Juan Carlos Mamani')
        ->assertSee('Pago del IMT');
});

test('con el IMT pagado ya no hay alerta', function () {
    $t = app(Transferencias::class)->iniciar($this->venta, '2026-11-06');
    $this->travelTo(CarbonImmutable::parse('2026-11-10'));
    app(Transferencias::class)->marcarPaso($t->pasos()->where('clave', 'imt')->first(), '2026-11-10', 'Fernando');

    $this->travelTo(CarbonImmutable::parse('2026-11-19'));
    expect($t->fresh('pasos')->alertaImt())->toBeFalse();
});

test('CA-10.3: al marcar el último paso la transferencia queda concluida y el vehículo entregado', function () {
    $t = app(Transferencias::class)->iniciar($this->venta, '2026-11-06');
    $pasos = $t->pasos()->where('clave', '!=', 'minuta')->get();

    $pantalla = Volt::test('transferencias.ver', ['transferencia' => $t]);
    foreach ($pasos as $i => $paso) {
        $pantalla->set("formulario.{$paso->id}.fecha", '2026-11-06')
            ->set("formulario.{$paso->id}.responsable", 'Gestor Quispe')
            ->call('marcar', $paso->id)
            ->assertHasNoErrors();

        if ($i < $pasos->count() - 1) {
            expect($t->fresh()->estado)->toBe('en_curso');
        }
    }

    expect($t->fresh()->estado)->toBe('concluida')
        ->and($t->fresh()->concluida_at)->not->toBeNull()
        ->and($this->venta->vehiculo->fresh()->estado)->toBe('entregado');
});

test('un paso exige responsable', function () {
    $t = app(Transferencias::class)->iniciar($this->venta, '2026-11-06');
    $imt = $t->pasos()->where('clave', 'imt')->first();

    Volt::test('transferencias.ver', ['transferencia' => $t])
        ->set("formulario.{$imt->id}.responsable", '')
        ->call('marcar', $imt->id)
        ->assertHasErrors("formulario.{$imt->id}.responsable");
});

test('no se inicia la transferencia dos veces ni de un vehículo no vendido', function () {
    app(Transferencias::class)->iniciar($this->venta, '2026-11-06');

    expect(fn () => app(Transferencias::class)->iniciar($this->venta->fresh(), '2026-11-06'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('el plazo del IMT es configurable por negocio', function () {
    $this->dueno->negocio->update(['plazo_imt_dias' => 5]);

    $t = app(Transferencias::class)->iniciar($this->venta->fresh(), '2026-11-06');
    expect($t->fecha_limite_imt->toDateString())->toBe('2026-11-13');
});

test('el cajero no gestiona transferencias', function () {
    $this->actingAs(usuarioCon(Roles::CAJERO, $this->dueno->negocio))
        ->get(route('transferencias.index'))
        ->assertForbidden();
});
