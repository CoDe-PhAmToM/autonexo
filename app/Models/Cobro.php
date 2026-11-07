<?php

namespace App\Models;

use App\Exceptions\RegistroInmutable;
use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\RegistraBitacora;
use App\Models\Concerns\SoloAnulable;
use App\Servicios\Cobros;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/**
 * PBI-07: cobro de cuotas en Bs o USD, en efectivo o por QR.
 * PBI-20 (CA-20): no se edita ni se borra; solo se anula con motivo y queda en la bitácora.
 */
class Cobro extends Model
{
    use PerteneceANegocio, RegistraBitacora;
    use SoloAnulable {
        anular as private anularRegistro;
    }

    public const MEDIOS = ['efectivo' => 'Efectivo', 'qr' => 'QR', 'carga_inicial' => 'Carga inicial'];

    protected $fillable = [
        'negocio_id', 'venta_id', 'user_id', 'medio', 'fecha_pago', 'moneda_pago', 'monto_pago', 'tc_aplicado',
        'fuente_tc', 'motivo_tc', 'monto_aplicado', 'estado', 'comprobante_path',
    ];

    protected function casts(): array
    {
        return [
            'monto_pago' => 'decimal:2',
            'monto_aplicado' => 'decimal:2',
            'tc_aplicado' => 'decimal:4',
            'fecha_pago' => 'date',
            'confirmado_at' => 'datetime',
            'anulado_at' => 'datetime',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cuotas(): BelongsToMany
    {
        return $this->belongsToMany(Cuota::class, 'cobro_cuota')->withPivot('monto')->orderBy('numero');
    }

    /** PBI-23: se registró el mismo día en que se recibió el pago. */
    public function registradoElMismoDia(): bool
    {
        return $this->fecha_pago?->toDateString() === $this->created_at->toDateString();
    }

    public function porConfirmar(): bool
    {
        return $this->estado === 'por_confirmar' && ! $this->estaAnulado();
    }

    /** CA-07.3: un usuario autorizado verifica el QR y recién entonces cuenta en la cartera. */
    public function confirmar(): void
    {
        if (! $this->porConfirmar()) {
            throw new RegistroInmutable('Solo se confirma un cobro por QR pendiente.');
        }

        DB::transaction(function () {
            $this->forceFill([
                'estado' => 'confirmado',
                'confirmado_por' => auth()->id(),
                'confirmado_at' => now(),
            ])->saveQuietly();

            $this->anotarEnBitacora('confirmado');
            app(Cobros::class)->recalcular($this->venta);
        });
    }

    /** CA-07.4: la anulación no borra el cobro y el saldo de las cuotas se recalcula. */
    public function anular(string $motivo): void
    {
        DB::transaction(function () use ($motivo) {
            $this->anularRegistro($motivo);
            app(Cobros::class)->recalcular($this->venta);
        });
    }

    public function simboloPago(): string
    {
        return $this->moneda_pago === 'BOB' ? 'Bs' : 'USD';
    }

    public function nombreEstado(): string
    {
        return match (true) {
            $this->estaAnulado() => 'Anulado',
            $this->estado === 'por_confirmar' => 'Por confirmar',
            default => 'Confirmado',
        };
    }

    public function colorEstado(): string
    {
        return match (true) {
            $this->estaAnulado() => 'zinc',
            $this->estado === 'por_confirmar' => 'amber',
            default => 'green',
        };
    }
}
