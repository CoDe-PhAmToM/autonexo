@php
    use App\Models\Vehiculo;

    app(\App\Servicios\Ventas::class)->vencerReservas(); // CA-05: el panel refleja el estado actual

    $porEstado = Vehiculo::query()
        ->selectRaw('estado, count(*) as total')
        ->groupBy('estado')
        ->pluck('total', 'estado');

    // CA-10.2: transferencias con el pago del IMT pendiente y el plazo cerca (o vencido).
    $alertasImt = auth()->user()->can('transferencias.gestionar')
        ? \App\Models\Transferencia::with('pasos', 'venta.vehiculo', 'venta.comprador')
            ->where('estado', 'en_curso')->get()->filter->alertaImt()
        : collect();

    // PBI-22 (pivote HP-03): recordatorio de cerrar la caja si hubo cobros hoy.
    $cobrosSinCerrar = auth()->user()->can('caja.cerrar')
        && ! \App\Models\CierreCaja::where('user_id', auth()->id())->whereDate('fecha', today())->exists()
        ? \App\Models\Cobro::where('user_id', auth()->id())->whereDate('created_at', today())->whereNull('anulado_at')->whereIn('medio', ['efectivo', 'qr'])->count()
        : 0;
@endphp

<x-layouts.app title="Panel">
    <div class="flex flex-col gap-6">
        <div>
            <flux:heading size="xl">Hola, {{ str(auth()->user()->name)->before(' ') }}</flux:heading>
            <flux:subheading>{{ auth()->user()->negocio?->nombre }} · {{ now()->translatedFormat('l j \d\e F \d\e Y') }}</flux:subheading>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (Vehiculo::ESTADOS as $clave => $nombre)
                <flux:card>
                    <div class="text-sm text-zinc-500">{{ $nombre }}</div>
                    <div class="mt-1 text-3xl font-semibold">{{ $porEstado[$clave] ?? 0 }}</div>
                    <div class="text-xs text-zinc-500">vehículos</div>
                </flux:card>
            @endforeach
        </div>

        @if ($cobrosSinCerrar > 0)
            <flux:callout icon="lock-open" color="amber">
                <flux:callout.heading>Todavía no cerraste la caja de hoy</flux:callout.heading>
                <flux:callout.text>
                    Registraste {{ $cobrosSinCerrar }} cobro(s). Al terminar el día, cuenta el efectivo y los QR y
                    <a class="underline" href="{{ route('caja.index') }}" wire:navigate>cierra la caja</a>.
                </flux:callout.text>
            </flux:callout>
        @endif

        @foreach ($alertasImt as $t)
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:callout.heading>
                    IMT: {{ $t->textoPlazoImt() }}
                    · límite {{ $t->fecha_limite_imt->format('d/m/Y') }}
                </flux:callout.heading>
                <flux:callout.text>
                    {{ $t->venta->vehiculo->descripcion() }} ({{ $t->venta->vehiculo->placa ?? 'sin placa' }}) · {{ $t->venta->comprador->nombre }} · paso pendiente: Pago del IMT.
                    <a class="underline" href="{{ route('transferencias.ver', $t) }}" wire:navigate>Ver transferencia</a>
                </flux:callout.text>
            </flux:callout>
        @endforeach

        <flux:callout icon="information-circle" heading="En construcción">
            <flux:callout.text>Aquí aparecerán el total por cobrar y las cuotas vencidas (etapas 4 y 5).</flux:callout.text>
        </flux:callout>
    </div>
</x-layouts.app>
