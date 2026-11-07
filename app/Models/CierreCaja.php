<?php

namespace App\Models;

use App\Exceptions\RegistroInmutable;
use App\Models\Concerns\PerteneceANegocio;
use App\Servicios\CalculadoraDePlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** PBI-22: cierre de caja de un cajero en un día. Una vez guardado no se modifica. */
class CierreCaja extends Model
{
    use PerteneceANegocio;

    protected $table = 'cierres_caja';

    /** combo => [etiqueta, símbolo] */
    public const COMBOS = [
        'efectivo_bob' => ['Efectivo en bolivianos', 'Bs'],
        'efectivo_usd' => ['Efectivo en dólares', 'USD'],
        'qr_bob' => ['QR en bolivianos', 'Bs'],
        'qr_usd' => ['QR en dólares', 'USD'],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        $casts = ['fecha' => 'date', 'cobros' => 'integer'];
        foreach (array_keys(self::COMBOS) as $combo) {
            foreach (['registrado', 'declarado', 'diferencia'] as $campo) {
                $casts["{$combo}_{$campo}"] = 'decimal:2';
            }
        }

        return $casts;
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RegistroInmutable('Un cierre de caja no se modifica.'));
        static::deleting(fn () => throw new RegistroInmutable('Un cierre de caja no se borra.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cuadra(): bool
    {
        foreach (array_keys(self::COMBOS) as $combo) {
            if (CalculadoraDePlan::aCentavos($this->{"{$combo}_diferencia"}) !== 0) {
                return false;
            }
        }

        return true;
    }
}
