<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\RegistraBitacora;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** PBI-05: reserva de un vehículo con anticipo y fecha límite. */
class Reserva extends Model
{
    use PerteneceANegocio, RegistraBitacora;

    public const ESTADOS = [
        'activa' => 'Activa',
        'vencida' => 'Vencida',
        'convertida' => 'Convertida en venta',
        'cancelada' => 'Cancelada',
    ];

    protected $fillable = ['negocio_id', 'vehiculo_id', 'comprador_id', 'user_id', 'anticipo', 'moneda', 'fecha_limite', 'estado'];

    protected function casts(): array
    {
        return ['anticipo' => 'decimal:2', 'fecha_limite' => 'date'];
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function comprador(): BelongsTo
    {
        return $this->belongsTo(Comprador::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
