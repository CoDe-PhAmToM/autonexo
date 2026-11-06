@php
    use App\Models\Vehiculo;

    app(\App\Servicios\Ventas::class)->vencerReservas(); // CA-05: el panel refleja el estado actual

    $porEstado = Vehiculo::query()
        ->selectRaw('estado, count(*) as total')
        ->groupBy('estado')
        ->pluck('total', 'estado');
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

        <flux:callout icon="information-circle" heading="En construcción">
            <flux:callout.text>Aquí aparecerán el total por cobrar, las cuotas vencidas y las transferencias pendientes (etapas 4 y 5).</flux:callout.text>
        </flux:callout>
    </div>
</x-layouts.app>
