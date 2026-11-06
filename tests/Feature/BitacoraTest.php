<?php

/**
 * PBI-20 — CA-20: un cobro no puede editarse ni borrarse; la anulación exige motivo
 * y queda en la bitácora con autor y fecha.
 *
 * La tabla de cobros llega con el PBI-07; aquí se prueba la regla con una tabla de ensayo
 * que tiene las mismas columnas de anulación. El PBI-07 solo agrega los traits al modelo Cobro.
 */

use App\Exceptions\RegistroInmutable;
use App\Models\Bitacora;
use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\RegistraBitacora;
use App\Models\Concerns\SoloAnulable;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CobroDeEnsayo extends Model
{
    use PerteneceANegocio, RegistraBitacora, SoloAnulable;

    protected $table = 'cobros_de_ensayo';
    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('cobros_de_ensayo', function (Blueprint $table) {
        $table->id();
        $table->foreignId('negocio_id');
        $table->decimal('monto_usd', 14, 2);
        $table->timestampTz('anulado_at')->nullable();
        $table->foreignId('anulado_por')->nullable();
        $table->text('motivo_anulacion')->nullable();
        $table->timestamps();
    });

    $this->cajero = usuarioCon(Roles::CAJERO);
    $this->actingAs($this->cajero);
    $this->cobro = CobroDeEnsayo::create(['monto_usd' => '500.00']);
});

test('un cobro no se puede editar', function () {
    $this->cobro->update(['monto_usd' => '50.00']);
})->throws(RegistroInmutable::class);

test('un cobro no se puede borrar', function () {
    $this->cobro->delete();
})->throws(RegistroInmutable::class);

test('la anulación exige un motivo', function (string $motivo) {
    $this->cobro->anular($motivo);
})->throws(ValidationException::class)->with(['vacío' => '', 'muy corto' => 'no']);

test('la anulación queda en la bitácora con autor, fecha y motivo', function () {
    $this->travelTo(now()->setTime(15, 30));

    $this->cobro->anular('Monto mal digitado por la cajera');

    $cobro = $this->cobro->fresh();
    expect($cobro->estaAnulado())->toBeTrue()
        ->and($cobro->monto_usd)->toBe('500.00')       // el monto original no se toca
        ->and($cobro->anulado_por)->toBe($this->cajero->id);

    $anulacion = $cobro->bitacora()->where('accion', 'anulado')->sole();
    expect($anulacion->user_id)->toBe($this->cajero->id)
        ->and($anulacion->motivo)->toBe('Monto mal digitado por la cajera')
        ->and($anulacion->created_at->format('H:i'))->toBe('15:30');
});

test('un cobro anulado no se puede anular de nuevo', function () {
    $this->cobro->anular('Primera anulación');
    $this->cobro->fresh()->anular('Segunda anulación');
})->throws(RegistroInmutable::class);

test('la bitácora tampoco se puede modificar ni borrar', function () {
    $evento = Bitacora::firstOrFail();

    expect(fn () => $evento->update(['motivo' => 'cambiado']))->toThrow(RegistroInmutable::class)
        ->and(fn () => $evento->delete())->toThrow(RegistroInmutable::class);
});

test('la base de datos bloquea cambios directos a la bitácora', function () {
    $id = Bitacora::firstOrFail()->id;

    DB::table('bitacora')->where('id', $id)->update(['motivo' => 'cambiado a mano']);
})->throws(QueryException::class, 'La bitácora no se modifica ni se borra');
