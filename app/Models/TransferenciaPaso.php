<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** PBI-10: un paso de la lista de control (cumplido cuando tiene fecha y responsable). */
class TransferenciaPaso extends Model
{
    protected $fillable = ['transferencia_id', 'orden', 'clave', 'nombre', 'fecha', 'responsable', 'user_id'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'orden' => 'integer'];
    }

    public function transferencia(): BelongsTo
    {
        return $this->belongsTo(Transferencia::class);
    }

    public function cumplido(): bool
    {
        return $this->fecha !== null && filled($this->responsable);
    }
}
