<?php

namespace App\Models\Concerns;

use App\Models\Bitacora;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Anota en la bitácora cada alta y cada cambio del modelo, con el valor anterior y el nuevo.
 * PBI-03 (CA-03): corregir la placa conserva el historial del vehículo.
 */
trait RegistraBitacora
{
    public static function bootRegistraBitacora(): void
    {
        static::created(fn ($modelo) => $modelo->anotarEnBitacora('creado'));

        static::updated(function ($modelo) {
            $cambios = [];
            foreach ($modelo->getChanges() as $campo => $nuevo) {
                if (in_array($campo, ['updated_at', 'created_at'], true)) {
                    continue;
                }
                $cambios[$campo] = ['antes' => $modelo->getOriginal($campo), 'despues' => $nuevo];
            }

            if ($cambios !== []) {
                $modelo->anotarEnBitacora('actualizado', $cambios);
            }
        });
    }

    public function anotarEnBitacora(string $accion, ?array $cambios = null, ?string $motivo = null): Bitacora
    {
        return Bitacora::create([
            'negocio_id' => $this->negocio_id,
            'user_id' => auth()->id(),
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'accion' => $accion,
            'motivo' => $motivo,
            'cambios' => $cambios,
        ]);
    }

    public function bitacora(): MorphMany
    {
        return $this->morphMany(Bitacora::class, 'auditable')->latest('id');
    }
}
