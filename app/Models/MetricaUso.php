<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;

/** PBI-23: un evento de uso (sin datos personales). */
class MetricaUso extends Model
{
    use PerteneceANegocio;

    protected $table = 'metricas_uso';

    public const UPDATED_AT = null;

    protected $fillable = ['negocio_id', 'evento', 'fecha', 'segundos', 'mismo_dia'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'segundos' => 'decimal:2', 'mismo_dia' => 'boolean'];
    }
}
