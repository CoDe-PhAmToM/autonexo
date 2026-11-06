<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\RegistraBitacora;
use App\Servicios\CalculadoraDePlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** PBI-06: venta al contado o a crédito con su plan de cuotas. */
class Venta extends Model
{
    use PerteneceANegocio, RegistraBitacora;

    public const MONEDAS = ['USD' => 'Dólares (USD)', 'BOB' => 'Bolivianos (Bs)'];

    public const REGLAS_TC = [
        'oficial' => 'Oficial del día del pago',
        'pactado' => 'Tipo de cambio pactado',
    ];

    protected $fillable = [
        'negocio_id', 'vehiculo_id', 'comprador_id', 'reserva_id', 'user_id', 'tipo', 'moneda',
        'regla_tc', 'tc_pactado', 'precio', 'inicial', 'numero_cuotas', 'fecha_venta',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'inicial' => 'decimal:2',
            'tc_pactado' => 'decimal:4',
            'numero_cuotas' => 'integer',
            'fecha_venta' => 'date',
        ];
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

    public function cobros(): HasMany
    {
        return $this->hasMany(Cobro::class)->latest('id');
    }

    public function transferencia(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Transferencia::class);
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(Cuota::class)->orderBy('numero');
    }

    /** Saldo pendiente de todas las cuotas, como texto con 2 decimales. */
    public function saldo(): string
    {
        $centavos = $this->cuotas->sum(fn (Cuota $c) => CalculadoraDePlan::aCentavos($c->saldo()));

        return CalculadoraDePlan::aTexto($centavos);
    }

    public function simboloMoneda(): string
    {
        return $this->moneda === 'BOB' ? 'Bs' : 'USD';
    }
}
