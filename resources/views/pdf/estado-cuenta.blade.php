@php
    use App\Support\Formato;
    $negocio = $venta->negocio;
    $sim = $venta->simboloMoneda();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Estado de cuenta · Venta N.º {{ $venta->id }}</title>
    @include('pdf.estilos')
</head>
<body>
    <div class="head">
        <span class="logo">AN</span>
        <strong>{{ $negocio->nombre }}</strong>
        <span class="muted">· {{ $negocio->ciudad }}@if ($negocio->nit) · NIT {{ $negocio->nit }}@endif @if ($negocio->telefono) · Tel. {{ $negocio->telefono }}@endif</span>
    </div>

    <h1>Estado de cuenta</h1>
    <div class="muted">Venta N.º {{ $venta->id }} · al {{ now()->format('d/m/Y H:i') }}</div>

    <table class="datos" style="margin-top: 10px;">
        <tr><td class="muted" style="width: 28%;">Comprador</td><td>{{ $venta->comprador->nombre }} (CI {{ $venta->comprador->ci }})</td></tr>
        <tr><td class="muted">Vehículo</td><td>{{ $venta->vehiculo->descripcion() }} · chasis {{ $venta->vehiculo->chasis }} · placa {{ $venta->vehiculo->placa ?? '—' }}</td></tr>
        <tr><td class="muted">Fecha de venta</td><td>{{ $venta->fecha_venta->format('d/m/Y') }}</td></tr>
        <tr><td class="muted">Precio</td><td>{{ $sim }} {{ Formato::monto($venta->precio) }} · cuota inicial {{ $sim }} {{ Formato::monto($venta->inicial) }}</td></tr>
        <tr><td class="muted">Saldo pendiente</td><td class="total">{{ $sim }} {{ Formato::monto($venta->saldo()) }}</td></tr>
    </table>

    <h2>Plan de cuotas</h2>
    <table class="tabla">
        <tr><th>N.º</th><th>Vencimiento</th><th class="num">Monto</th><th class="num">Pagado</th><th class="num">Saldo</th><th>Estado</th></tr>
        @foreach ($venta->cuotas as $cuota)
            <tr>
                <td>{{ $cuota->numero }}</td>
                <td>{{ $cuota->vencimiento->format('d/m/Y') }}</td>
                <td class="num">{{ Formato::monto($cuota->monto) }}</td>
                <td class="num">{{ Formato::monto($cuota->pagado) }}</td>
                <td class="num">{{ Formato::monto($cuota->saldo()) }}</td>
                <td>{{ $cuota->nombreEstado() }}</td>
            </tr>
        @endforeach
    </table>

    @php $cobros = $venta->cobros->filter(fn ($c) => ! $c->estaAnulado()); @endphp
    @if ($cobros->isNotEmpty())
        <h2>Pagos registrados</h2>
        <table class="tabla">
            <tr><th>N.º</th><th>Fecha</th><th>Medio</th><th class="num">Recibido</th><th class="num">Aplicado</th><th>Estado</th></tr>
            @foreach ($cobros as $cobro)
                <tr>
                    <td>{{ $cobro->id }}</td>
                    <td>{{ $cobro->fecha_pago?->format('d/m/Y') }}</td>
                    <td>{{ \App\Models\Cobro::MEDIOS[$cobro->medio] }}</td>
                    <td class="num">{{ $cobro->simboloPago() }} {{ Formato::monto($cobro->monto_pago) }}</td>
                    <td class="num">{{ $sim }} {{ Formato::monto($cobro->monto_aplicado) }}</td>
                    <td>{{ $cobro->nombreEstado() }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="pie">
        Los pagos por QR pendientes de confirmación no se descuentan del saldo.&nbsp; <strong>Este documento no sustituye a una factura.</strong>
        Generado por AutoNexo con el consentimiento del comprador registrado el {{ $venta->comprador->consentimiento_at?->format('d/m/Y') }}.
    </div>
</body>
</html>
