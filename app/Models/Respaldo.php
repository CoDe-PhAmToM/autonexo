<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** PBI-21: resultado de cada respaldo (los fallidos quedan visibles). */
class Respaldo extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'estado', 'archivo', 'bytes', 'mensaje'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'bytes' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exitoso(): bool
    {
        return $this->estado === 'exitoso';
    }

    public function tamano(): string
    {
        if (! $this->bytes) {
            return '—';
        }

        return $this->bytes >= 1048576
            ? number_format($this->bytes / 1048576, 1, ',', '.').' MB'
            : number_format($this->bytes / 1024, 0, ',', '.').' KB';
    }
}
