<?php

namespace App\Models;

use App\Exceptions\RegistroInmutable;
use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** PBI-11: un movimiento del kárdex. No se edita ni se borra. */
class Kardex extends Model
{
    use PerteneceANegocio;

    protected $table = 'kardex';

    public const UPDATED_AT = null;

    protected $fillable = ['negocio_id', 'repuesto_id', 'user_id', 'tipo', 'cantidad', 'saldo', 'detalle'];

    protected function casts(): array
    {
        return ['cantidad' => 'integer', 'saldo' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RegistroInmutable('El kárdex no se modifica.'));
        static::deleting(fn () => throw new RegistroInmutable('El kárdex no se borra.'));
    }

    public function repuesto(): BelongsTo
    {
        return $this->belongsTo(Repuesto::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
