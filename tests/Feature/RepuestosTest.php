<?php

/**
 * PBI-11 — CA-11: entradas y salidas actualizan el saldo; una salida no deja stock
 * negativo; alerta bajo el mínimo.
 */

use App\Exceptions\RegistroInmutable;
use App\Models\Repuesto;
use App\Support\Roles;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->almacenero = usuarioCon(Roles::ALMACENERO);
    $this->actingAs($this->almacenero);
    $this->repuesto = Repuesto::factory()->for($this->almacenero->negocio)->create(['stock_minimo' => 5]);
});

test('entradas y salidas actualizan el saldo y quedan en el kárdex', function () {
    $this->repuesto->mover('entrada', 10, 'Compra a proveedor');
    $this->repuesto->mover('salida', 3, 'Venta mostrador');

    expect($this->repuesto->fresh()->stock)->toBe(7);

    $kardex = $this->repuesto->movimientos()->get()->reverse()->values();
    expect($kardex->pluck('saldo')->all())->toBe([10, 7])
        ->and($kardex->pluck('tipo')->all())->toBe(['entrada', 'salida'])
        ->and($kardex->first()->user_id)->toBe($this->almacenero->id);
});

test('una salida mayor al stock se rechaza y el stock no cambia', function () {
    $this->repuesto->mover('entrada', 2);

    expect(fn () => $this->repuesto->mover('salida', 3))->toThrow(ValidationException::class);
    expect($this->repuesto->fresh()->stock)->toBe(2)
        ->and($this->repuesto->movimientos()->count())->toBe(1);
});

test('la base de datos tampoco acepta stock negativo', function () {
    DB::table('repuestos')->where('id', $this->repuesto->id)->update(['stock' => -1]);
})->throws(QueryException::class);

test('desde la pantalla, la salida sin stock muestra el error', function () {
    $this->repuesto->mover('entrada', 1);

    Volt::test('repuestos.ver', ['repuesto' => $this->repuesto])
        ->set('tipo', 'salida')
        ->set('cantidad', '4')
        ->call('registrar')
        ->assertHasErrors('cantidad');

    expect($this->repuesto->fresh()->stock)->toBe(1);
});

test('el repuesto bajo el mínimo se alerta en el listado', function () {
    $this->repuesto->update(['nombre' => 'Pastillas de freno']);
    $this->repuesto->mover('entrada', 2); // mínimo 5

    $this->get(route('repuestos.index'))
        ->assertOk()
        ->assertSee('1 repuesto está bajo el stock mínimo');

    Volt::test('repuestos.index')
        ->set('soloBajoMinimo', true)
        ->assertSee('Pastillas de freno');
});

test('crear un repuesto con stock inicial deja el primer movimiento', function () {
    Volt::test('repuestos.index')
        ->set('codigo', 'fil-ace-01')
        ->set('nombre', 'Filtro de aceite')
        ->set('stock_inicial', '12')
        ->set('stock_minimo', '4')
        ->call('crear')
        ->assertHasNoErrors();

    $nuevo = Repuesto::where('codigo', 'FIL-ACE-01')->sole();
    expect($nuevo->stock)->toBe(12)
        ->and($nuevo->movimientos()->sole()->detalle)->toBe('Stock inicial');
});

test('el kárdex no se edita', function () {
    $mov = $this->repuesto->mover('entrada', 5);
    $mov->update(['cantidad' => 50]);
})->throws(RegistroInmutable::class);

test('vendedor y cajero no entran a repuestos', function (string $rol) {
    $this->actingAs(usuarioCon($rol))->get(route('repuestos.index'))->assertForbidden();
})->with([Roles::VENDEDOR, Roles::CAJERO]);

test('no se ven repuestos de otro negocio', function () {
    $this->actingAs(usuarioCon(Roles::ALMACENERO))
        ->get(route('repuestos.ver', $this->repuesto))
        ->assertNotFound();
});
