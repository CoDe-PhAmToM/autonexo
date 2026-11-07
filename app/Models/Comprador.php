<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\RegistraBitacora;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comprador extends Model
{
    /** @use HasFactory<\Database\Factories\CompradorFactory> */
    use HasFactory, PerteneceANegocio, RegistraBitacora;

    protected $table = 'compradores';

    protected $fillable = ['negocio_id', 'nombre', 'ci', 'telefono', 'consentimiento_comprobante', 'consentimiento_at'];

    protected function casts(): array
    {
        return ['consentimiento_comprobante' => 'boolean', 'consentimiento_at' => 'datetime'];
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    /**
     * PBI-12 (CA-12): el comprador autoriza recibir comprobantes y estado de cuenta.
     * El cambio queda en la bitácora con autor y fecha.
     */
    public function registrarConsentimiento(bool $acepta): void
    {
        $this->update([
            'consentimiento_comprobante' => $acepta,
            'consentimiento_at' => $acepta ? now() : null,
        ]);
    }

    /** Número para enlaces de WhatsApp (Bolivia: 591 + 8 dígitos), o null. */
    public function whatsapp(): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $this->telefono);

        return match (true) {
            strlen($digitos) === 8 => '591'.$digitos,
            strlen($digitos) === 11 && str_starts_with($digitos, '591') => $digitos,
            default => null,
        };
    }
}
