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
