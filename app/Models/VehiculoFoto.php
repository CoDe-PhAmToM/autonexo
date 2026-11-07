<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una foto de la galería de un vehículo (la de menor orden es la portada). */
class VehiculoFoto extends Model
{
    use PerteneceANegocio;

    protected $table = 'vehiculo_fotos';

    protected $fillable = ['negocio_id', 'vehiculo_id', 'path', 'orden'];

    protected function casts(): array
    {
        return ['orden' => 'integer'];
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    /**
     * Dirección de la foto con una marca única de su archivo: si la foto cambia (o la base se
     * reinicia y el número se reutiliza), el navegador no muestra una copia vieja de su caché.
     */
    public function url(): string
    {
        return route('vehiculos.fotos.ver', [$this->vehiculo_id, $this->id]).'?v='.substr(md5($this->path), 0, 10);
    }
}
