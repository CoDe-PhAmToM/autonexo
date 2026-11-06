<?php

namespace App\Servicios;

use App\Models\Transferencia;
use App\Models\TransferenciaPaso;
use App\Models\Venta;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PBI-10 — seguimiento de la transferencia (CA-10.1 a 10.3).
 */
final class Transferencias
{
    /**
     * CA-10.1: al registrar la entrega con la fecha de la minuta se crea la lista de control
     * y la fecha límite del IMT en días hábiles (lunes a viernes).
     */
    public function iniciar(Venta $venta, string $fechaMinuta): Transferencia
    {
        return DB::transaction(function () use ($venta, $fechaMinuta) {
            $venta->load('vehiculo', 'transferencia');

            if ($venta->vehiculo->estado !== 'vendido') {
                throw ValidationException::withMessages(['fecha_minuta' => "El vehículo está «{$venta->vehiculo->nombreEstado()}»; la transferencia se inicia con un vehículo vendido."]);
            }
            if ($venta->transferencia) {
                throw ValidationException::withMessages(['fecha_minuta' => 'Esta venta ya tiene su transferencia en curso.']);
            }

            $minuta = CarbonImmutable::parse($fechaMinuta);

            $transferencia = Transferencia::create([
                'venta_id' => $venta->id,
                'user_id' => auth()->id(),
                'fecha_entrega' => today(),
                'fecha_minuta' => $minuta,
                'fecha_limite_imt' => self::sumarDiasHabiles($minuta, $venta->negocio->plazo_imt_dias ?? 10),
                'estado' => 'en_curso',
            ]);

            $orden = 1;
            foreach (Transferencia::PASOS as $clave => $nombre) {
                $transferencia->pasos()->create([
                    'orden' => $orden++,
                    'clave' => $clave,
                    'nombre' => $nombre,
                    // La minuta ya está firmada cuando se registra la entrega.
                    'fecha' => $clave === 'minuta' ? $minuta : null,
                    'responsable' => $clave === 'minuta' ? auth()->user()?->name : null,
                    'user_id' => $clave === 'minuta' ? auth()->id() : null,
                ]);
            }

            return $transferencia;
        });
    }

    /**
     * Marca un paso con fecha y responsable.
     * CA-10.3: al completar el último, la transferencia queda «Concluida» y el vehículo «Entregado».
     */
    public function marcarPaso(TransferenciaPaso $paso, string $fecha, string $responsable): void
    {
        DB::transaction(function () use ($paso, $fecha, $responsable) {
            $transferencia = $paso->transferencia()->with('venta.vehiculo')->firstOrFail();

            if ($transferencia->concluida()) {
                throw ValidationException::withMessages(['paso' => 'La transferencia ya está concluida.']);
            }
            if (trim($responsable) === '') {
                throw ValidationException::withMessages(['responsable' => 'Indica el responsable del paso.']);
            }
            if (CarbonImmutable::parse($fecha)->gt(today())) {
                throw ValidationException::withMessages(['fecha' => 'La fecha del paso no puede ser futura.']);
            }

            $paso->update(['fecha' => $fecha, 'responsable' => trim($responsable), 'user_id' => auth()->id()]);

            $transferencia->load('pasos');
            if ($transferencia->pasos->every->cumplido()) {
                $transferencia->update(['estado' => 'concluida', 'concluida_at' => now()]);
                $transferencia->venta->vehiculo->update(['estado' => 'entregado']);
            }
        });
    }

    public static function sumarDiasHabiles(CarbonImmutable $desde, int $dias): CarbonImmutable
    {
        return $desde->addWeekdays($dias);
    }
}
