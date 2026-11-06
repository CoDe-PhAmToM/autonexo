<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comprador extends Model
{
    /** @use HasFactory<\Database\Factories\CompradorFactory> */
    use HasFactory, PerteneceANegocio;

    protected $table = 'compradores';

    protected $fillable = ['negocio_id', 'nombre', 'ci', 'telefono', 'consentimiento_comprobante'];

    protected function casts(): array
    {
        return ['consentimiento_comprobante' => 'boolean'];
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }
}
