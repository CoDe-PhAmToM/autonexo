<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** PBI-07: tipo de cambio oficial del día (Bs por 1 USD), cargado una vez por día. */
class TipoCambio extends Model
{
    use PerteneceANegocio;

    protected $table = 'tipos_cambio';

    protected $fillable = ['negocio_id', 'fecha', 'valor', 'fuente', 'user_id'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'valor' => 'decimal:4'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** El de hoy o, si no se cargó, el último conocido. */
    public static function vigente(): ?self
    {
        return static::whereDate('fecha', '<=', today())->latest('fecha')->first();
    }

    public function esDeHoy(): bool
    {
        return $this->fecha->isToday();
    }
}
