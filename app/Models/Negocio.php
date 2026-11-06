<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Negocio extends Model
{
    /** @use HasFactory<\Database\Factories\NegocioFactory> */
    use HasFactory;

    protected $fillable = ['nombre', 'nit', 'ciudad', 'telefono', 'plazo_imt_dias'];

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
