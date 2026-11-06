<?php

use App\Models\Vehiculo;
use App\Support\Formato;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/**
 * PBI-03: ficha del vehículo con su historial (bitácora).
 */
new #[Title('Vehículo')] class extends Component {
    public Vehiculo $vehiculo;

    public const CAMPOS = [
        'chasis' => 'Chasis', 'placa' => 'Placa', 'marca' => 'Marca', 'modelo' => 'Modelo',
        'anio' => 'Año', 'color' => 'Color', 'costo_usd' => 'Costo USD', 'precio_usd' => 'Precio USD',
        'estado' => 'Estado', 'foto_path' => 'Foto', 'observaciones' => 'Observaciones',
    ];

    public function cancelarReserva(\App\Servicios\Ventas $ventas): void
    {
        abort_unless(auth()->user()->can('ventas.registrar'), 403);

        $reserva = $this->vehiculo->reservaActiva()->firstOrFail();
        $ventas->cancelarReserva($reserva);
        $this->vehiculo->refresh();

        session()->flash('estado', 'Reserva cancelada; el vehículo vuelve a exhibición.');
        $this->redirectRoute('vehiculos.ver', $this->vehiculo, navigate: true);
    }

    public function with(): array
    {
        return [
            'historial' => $this->vehiculo->bitacora()->with('user')->get(),
            'campos' => self::CAMPOS,
            'reserva' => $this->vehiculo->reservaActiva()->with('comprador')->first(),
            'venta' => $this->vehiculo->venta()->with('comprador')->first(),
        ];
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $vehiculo->descripcion() }}</flux:heading>
            <flux:subheading class="font-mono">{{ $vehiculo->chasis }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button :href="route('vehiculos.index')" wire:navigate variant="ghost" icon="arrow-left">Volver</flux:button>
            @can('vehiculos.gestionar')
                <flux:button :href="route('vehiculos.editar', $vehiculo)" wire:navigate icon="pencil-square">Corregir</flux:button>
            @endcan
            @can('ventas.registrar')
                @if ($vehiculo->estado === 'en_exhibicion')
                    <flux:button :href="route('vehiculos.reservar', $vehiculo)" wire:navigate icon="bookmark">Reservar</flux:button>
                @endif
                @if (in_array($vehiculo->estado, ['en_exhibicion', 'reservado']))
                    <flux:button :href="route('ventas.crear', $vehiculo)" wire:navigate variant="primary" icon="banknotes">Vender</flux:button>
                @endif
            @endcan
            @if ($venta)
                <flux:button :href="route('ventas.ver', $venta)" wire:navigate variant="primary" icon="document-text">Ver venta</flux:button>
            @endif
        </div>
    </div>

    @if (session('estado'))
        <flux:callout variant="success" icon="check-circle" :heading="session('estado')" />
    @endif

    @if ($reserva)
        <flux:callout icon="bookmark" color="amber">
            <flux:callout.heading>Reservado para {{ $reserva->comprador->nombre }} (CI {{ $reserva->comprador->ci }})</flux:callout.heading>
            <flux:callout.text>
                Anticipo de {{ $reserva->moneda === 'BOB' ? Formato::bs($reserva->anticipo) : Formato::usd($reserva->anticipo) }}
                · vence el {{ $reserva->fecha_limite->format('d/m/Y') }}
            </flux:callout.text>
            @can('ventas.registrar')
                <x-slot name="actions">
                    <flux:button size="sm" wire:click="cancelarReserva" wire:confirm="¿Cancelar la reserva? El vehículo vuelve a exhibición.">Cancelar reserva</flux:button>
                </x-slot>
            @endcan
        </flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="lg:col-span-2">
            <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm sm:grid-cols-3">
                <div><dt class="text-zinc-500">Estado</dt><dd><flux:badge size="sm" :color="$vehiculo->colorEstado()">{{ $vehiculo->nombreEstado() }}</flux:badge></dd></div>
                <div><dt class="text-zinc-500">Placa</dt><dd class="font-medium">{{ $vehiculo->placa ?? '—' }}</dd></div>
                <div><dt class="text-zinc-500">Color</dt><dd class="font-medium">{{ $vehiculo->color ?? '—' }}</dd></div>
                <div><dt class="text-zinc-500">Precio</dt><dd class="font-medium">{{ Formato::usd($vehiculo->precio_usd) }}</dd></div>
                @can('vehiculos.gestionar')
                    <div><dt class="text-zinc-500">Costo</dt><dd class="font-medium">{{ Formato::usd($vehiculo->costo_usd) }}</dd></div>
                @endcan
                <div><dt class="text-zinc-500">Registrado</dt><dd class="font-medium">{{ $vehiculo->created_at->format('d/m/Y') }}</dd></div>
                @if ($vehiculo->observaciones)
                    <div class="col-span-full"><dt class="text-zinc-500">Observaciones</dt><dd>{{ $vehiculo->observaciones }}</dd></div>
                @endif
            </dl>
        </flux:card>

        <flux:card class="flex items-center justify-center p-2">
            @if ($vehiculo->foto_path)
                <img src="{{ route('vehiculos.foto', $vehiculo) }}" alt="Foto de {{ $vehiculo->descripcion() }}" class="max-h-64 rounded-lg object-contain">
            @else
                <div class="py-10 text-sm text-zinc-500">Sin foto</div>
            @endif
        </flux:card>
    </div>

    <flux:card>
        <flux:heading size="lg" class="mb-3">Historial</flux:heading>
        <ol class="flex flex-col gap-3 text-sm">
            @foreach ($historial as $evento)
                <li class="border-l-2 border-zinc-200 pl-3 dark:border-zinc-700">
                    <div class="text-zinc-500">
                        {{ $evento->created_at->format('d/m/Y H:i') }} · {{ $evento->user?->name ?? 'Sistema' }}
                    </div>
                    <div class="font-medium">
                        @if ($evento->accion === 'creado')
                            Vehículo registrado
                        @elseif ($evento->accion === 'actualizado')
                            @foreach ($evento->cambios as $campo => $cambio)
                                <div>{{ $campos[$campo] ?? $campo }}: @php
                                    $legible = fn ($v) => $campo === 'estado' ? (Vehiculo::ESTADOS[$v] ?? $v) : ($v ?? '—');
                                @endphp
                                <div>{{ $campos[$campo] ?? $campo }}: <span class="text-zinc-500 line-through">{{ $legible($cambio['antes']) }}</span> → {{ $legible($cambio['despues']) }}</div>
                            @endforeach
                        @else
                            {{ ucfirst($evento->accion) }} @if ($evento->motivo) — {{ $evento->motivo }} @endif
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </flux:card>
</div>
