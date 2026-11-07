@php
    use App\Support\Formato;
    $venta = $cobro->venta;
    $negocio = $venta->negocio;
    $sim = $venta->simboloMoneda();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante de pago N.º {{ $cobro->id }}</title>
    @include('pdf.estilos')
</head>
<body>
    <div class="head">
        @include('pdf.logo')
        <strong>{{ $negocio->nombre }}</strong>
        <span class="muted">· {{ $negocio->ciudad }}@if ($negocio->nit) · NIT {{ $negocio->nit }}@endif @if ($negocio->telefono) · Tel. {{ $negocio->telefono }}@endif</span>
    </div>

    <h1>Comprobante de pago N.º {{ $cobro->id }}</h1>
    <div class="muted">Venta N.º {{ $venta->id }} · emitido el {{ now()->format('d/m/Y H:i') }}</div>

    @if ($cobro->estaAnulado())
        <p><span class="sello">ANULADO</span></p>
        <p>Motivo: {{ $cobro->motivo_anulacion }} ({{ $cobro->anulado_at->format('d/m/Y H:i') }})</p>
    @elseif ($cobro->estado === 'por_confirmar')
        <p><strong>Pago por QR pendiente de confirmación.</strong> Se descontará del saldo cuando el negocio verifique el depósito.</p>
    @endif

    <table class="datos" style="margin-top: 10px;">
        <tr><td class="muted" style="width: 28%;">Comprador</td><td>{{ $venta->comprador->nombre }} (CI {{ $venta->comprador->ci }})</td></tr>
        <tr><td class="muted">Vehículo</td><td>{{ $venta->vehiculo->descripcion() }} · chasis {{ $venta->vehiculo->chasis }} · placa {{ $venta->vehiculo->placa ?? '—' }}</td></tr>
        <tr><td class="muted">Fecha del pago</td><td>{{ $cobro->fecha_pago?->format('d/m/Y') }}</td></tr>
        <tr><td class="muted">Medio</td><td>{{ \App\Models\Cobro::MEDIOS[$cobro->medio] }}</td></tr>
        <tr><td class="muted">Monto recibido</td><td class="total">{{ $cobro->simboloPago() }} {{ Formato::monto($cobro->monto_pago) }}</td></tr>
        @if ($cobro->tc_aplicado)
            <tr><td class="muted">Tipo de cambio</td><td>{{ Formato::monto($cobro->tc_aplicado) }} Bs por USD ({{ $cobro->fuente_tc }})</td></tr>
        @endif
        <tr><td class="muted">Equivale a</td><td><strong>{{ $sim }} {{ Formato::monto($cobro->monto_aplicado) }}</strong></td></tr>
        <tr><td class="muted">Registró</td><td>{{ $cobro->user?->name }} · {{ $cobro->created_at->format('d/m/Y H:i') }}</td></tr>
    </table>

    <h2>Cuotas pagadas con este cobro</h2>
    <table class="tabla">
        <tr><th>Cuota</th><th>Vencimiento</th><th class="num">Aplicado</th><th class="num">Saldo de la cuota</th></tr>
        @foreach ($cobro->cuotas as $cuota)
            <tr>
                <td>{{ $cuota->numero }} de {{ $venta->numero_cuotas }}</td>
                <td>{{ $cuota->vencimiento->format('d/m/Y') }}</td>
                <td class="num">{{ $sim }} {{ Formato::monto($cuota->pivot->monto) }}</td>
                <td class="num">{{ $sim }} {{ Formato::monto($cuota->saldo()) }}</td>
            </tr>
        @endforeach
    </table>

    <p style="margin-top: 14px;">Saldo pendiente de la venta: <span class="total">{{ $sim }} {{ Formato::monto($venta->saldo()) }}</span></p>

    <div class="pie">
        Este comprobante registra un pago a cuenta de la venta indicada.&nbsp; <strong>No sustituye a una factura.</strong>
        Generado por AutoNexo con el consentimiento del comprador registrado el {{ $venta->comprador->consentimiento_at?->format('d/m/Y') }}.
    </div>
</body>
</html>
