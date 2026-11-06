<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use App\Servicios\CalculadoraDePlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** PBI-06: una cuota del plan. "pagado" lo actualizan los cobros (PBI-07). */
class Cuota extends Model
{
    use PerteneceANegocio;

    public const COLORES = ['pendiente' => 'zinc', 'parcial' => 'amber', 'pagada' => 'green', 'vencida' => 'red'];

    protected $fillable = ['negocio_id', 'venta_id', 'numero', 'vencimiento', 'monto', 'pagado'];

    protected function casts(): array
    {
        return ['numero' => 'integer', 'vencimiento' => 'date', 'monto' => 'decimal:2', 'pagado' => 'decimal:2'];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function saldo(): string
    {
        return CalculadoraDePlan::aTexto(
            CalculadoraDePlan::aCentavos($this->monto) - CalculadoraDePlan::aCentavos($this->pagado)
        );
    }

    /** pendiente | parcial | pagada | vencida */
    public function estado(): string
    {
        $pagado = CalculadoraDePlan::aCentavos($this->pagado);
        $monto = CalculadoraDePlan::aCentavos($this->monto);

        return match (true) {
            $pagado >= $monto => 'pagada',
            $this->vencimiento->lt(today()) => 'vencida',
            $pagado > 0 => 'parcial',
            default => 'pendiente',
        };
    }

    public function nombreEstado(): string
    {
        return ['pendiente' => 'Pendiente', 'parcial' => 'Pago parcial', 'pagada' => 'Pagada', 'vencida' => 'Vencida'][$this->estado()];
    }
}
