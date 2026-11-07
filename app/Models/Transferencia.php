<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\RegistraBitacora;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** PBI-10: seguimiento de la transferencia de propiedad de un vehículo vendido. */
class Transferencia extends Model
{
    use PerteneceANegocio, RegistraBitacora;

    /** Pasos por defecto (Entregable, PBI-10). */
    public const PASOS = [
        'minuta' => 'Minuta firmada',
        'imt' => 'Pago del IMT',
        'notaria' => 'Protocolización notarial',
        'registro' => 'Registro (Derechos Reales)',
        'transito' => 'Trámite en Tránsito',
    ];

    /** Días hábiles antes del límite del IMT en que aparece la alerta (CA-10.2). */
    public const DIAS_ALERTA = 2;

    protected $fillable = ['negocio_id', 'venta_id', 'user_id', 'fecha_entrega', 'fecha_minuta', 'fecha_limite_imt', 'estado', 'concluida_at'];

    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'date',
            'fecha_minuta' => 'date',
            'fecha_limite_imt' => 'date',
            'concluida_at' => 'datetime',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function pasos(): HasMany
    {
        return $this->hasMany(TransferenciaPaso::class)->orderBy('orden');
    }

    public function pasoImt(): ?TransferenciaPaso
    {
        return $this->pasos->firstWhere('clave', 'imt');
    }

    public function concluida(): bool
    {
        return $this->estado === 'concluida';
    }

    /** Días hábiles (sin fines de semana ni feriados) que faltan para el límite del IMT; negativo si ya venció. */
    public function diasHabilesParaImt(): int
    {
        $hoy = CarbonImmutable::today();
        $limite = CarbonImmutable::parse($this->fecha_limite_imt);

        return $hoy->lte($limite)
            ? \App\Support\Feriados::diasHabilesEntre($hoy, $limite)
            : -\App\Support\Feriados::diasHabilesEntre($limite, $hoy);
    }

    /** "IMT vencido", "falta 1 día hábil", "faltan 3 días hábiles". */
    public function textoPlazoImt(): string
    {
        $dias = $this->diasHabilesParaImt();

        return match (true) {
            $dias < 0 => 'IMT vencido',
            $dias === 0 => 'el IMT vence hoy',
            $dias === 1 => 'falta 1 día hábil',
            default => "faltan {$dias} días hábiles",
        };
    }

    /** CA-10.2: el IMT sigue pendiente y faltan 2 días hábiles o menos (o ya venció). */
    public function alertaImt(): bool
    {
        $imt = $this->pasoImt();

        return ! $this->concluida() && $imt && ! $imt->cumplido() && $this->diasHabilesParaImt() <= self::DIAS_ALERTA;
    }
}
