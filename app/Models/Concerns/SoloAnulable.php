<?php

namespace App\Models\Concerns;

use App\Exceptions\RegistroInmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PBI-20 (CA-20): un registro con este trait no se edita ni se borra.
 * La única corrección posible es anularlo con un motivo, y eso queda en la bitácora
 * con su autor y su fecha. Lo usarán los cobros (PBI-07).
 *
 * La tabla necesita las columnas: anulado_at, anulado_por y motivo_anulacion.
 * El modelo también debe usar RegistraBitacora.
 */
trait SoloAnulable
{
    public static function bootSoloAnulable(): void
    {
        static::updating(function () {
            throw new RegistroInmutable('Este registro no se puede editar: anúlelo indicando el motivo.');
        });

        static::deleting(function () {
            throw new RegistroInmutable('Este registro no se puede borrar: anúlelo indicando el motivo.');
        });
    }

    public function anular(string $motivo): void
    {
        $motivo = trim($motivo);

        if (mb_strlen($motivo) < 5) {
            throw ValidationException::withMessages(['motivo' => 'Indique el motivo de la anulación (mínimo 5 caracteres).']);
        }

        if ($this->estaAnulado()) {
            throw new RegistroInmutable('Este registro ya fue anulado.');
        }

        DB::transaction(function () use ($motivo) {
            // saveQuietly: la anulación es el único cambio permitido y no pasa por el bloqueo de "updating".
            $this->forceFill([
                'anulado_at' => now(),
                'anulado_por' => auth()->id(),
                'motivo_anulacion' => $motivo,
            ])->saveQuietly();

            $this->anotarEnBitacora('anulado', null, $motivo);
        });
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_at !== null;
    }
}
