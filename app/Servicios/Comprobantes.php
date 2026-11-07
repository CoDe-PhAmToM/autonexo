<?php

namespace App\Servicios;

use App\Models\Cobro;
use App\Models\Venta;
use App\Support\Formato;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * PBI-12 (CA-12): comprobante ligado al cobro y estado de cuenta actualizado.
 * Sin consentimiento registrado del comprador no se genera ni se envía. No sustituye a una factura.
 */
final class Comprobantes
{
    public function pdfDelCobro(Cobro $cobro): Response
    {
        $cobro->load('venta.comprador', 'venta.vehiculo', 'venta.negocio', 'venta.cuotas', 'cuotas', 'user');
        $this->exigirConsentimiento($cobro->venta);

        return Pdf::loadView('pdf.comprobante', ['cobro' => $cobro])
            ->setPaper('a5')
            ->stream("comprobante-{$cobro->id}.pdf");
    }

    public function pdfEstadoDeCuenta(Venta $venta): Response
    {
        $venta->load('comprador', 'vehiculo', 'negocio', 'cuotas', 'cobros');
        $this->exigirConsentimiento($venta);

        return Pdf::loadView('pdf.estado-cuenta', ['venta' => $venta])
            ->setPaper('letter')
            ->stream("estado-de-cuenta-venta-{$venta->id}.pdf");
    }

    /**
     * Enlace firmado para que el comprador descargue su comprobante sin iniciar sesión.
     * Vence en 7 días y no se puede modificar para ver otro cobro.
     */
    public function enlacePublico(Cobro $cobro): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute('comprobantes.publico', now()->addDays(7), ['cobro' => $cobro->id]);
    }

    /**
     * Enlace de WhatsApp con el resumen del pago y el enlace para descargar el PDF.
     * (WhatsApp no permite adjuntar archivos desde un enlace; el comprador lo descarga.)
     */
    public function enlaceWhatsApp(Cobro $cobro): ?string
    {
        $venta = $cobro->venta;
        $numero = $venta->comprador->whatsapp();

        if (! $venta->comprador->consentimiento_comprobante || ! $numero || $cobro->estaAnulado()) {
            return null;
        }

        $texto = sprintf(
            "Hola %s, %s registró tu pago N.º %d del %s por %s %s (%s). Saldo pendiente: %s %s. Descarga tu comprobante (válido 7 días): %s . Este mensaje no sustituye a una factura.",
            $venta->comprador->nombre,
            $venta->negocio->nombre,
            $cobro->id,
            $cobro->fecha_pago->format('d/m/Y'),
            $cobro->simboloPago(),
            Formato::monto($cobro->monto_pago),
            $cobro->estado === 'por_confirmar' ? 'QR por confirmar' : 'confirmado',
            $venta->simboloMoneda(),
            Formato::monto($venta->saldo()),
            $this->enlacePublico($cobro),
        );

        return 'https://wa.me/'.$numero.'?text='.rawurlencode($texto);
    }

    private function exigirConsentimiento(Venta $venta): void
    {
        abort_unless(
            $venta->comprador->consentimiento_comprobante,
            403,
            'El comprador no autorizó recibir comprobantes: registra su consentimiento en la venta.',
        );
    }
}
