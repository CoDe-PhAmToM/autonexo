<?php

namespace App\Models;

use App\Exceptions\RegistroInmutable;
use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * PBI-20: una fila por acción (creado, actualizado, anulado) con autor y fecha.
 */
class Bitacora extends Model
{
    use PerteneceANegocio;

    protected $table = 'bitacora';

    public const UPDATED_AT = null;

    protected $fillable = ['negocio_id', 'user_id', 'auditable_type', 'auditable_id', 'accion', 'motivo', 'cambios'];

    protected function casts(): array
    {
        return [
            'cambios' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RegistroInmutable('La bitácora no se modifica.'));
        static::deleting(fn () => throw new RegistroInmutable('La bitácora no se borra.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
