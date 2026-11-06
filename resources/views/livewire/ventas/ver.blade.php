<?php

use App\Models\Venta;
use App\Support\Formato;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/** PBI-06: ficha de la venta con su plan de cuotas. */
new #[Title('Venta')] class extends Component {
    public Venta $venta;

    public function mount(): void
    {
        $this->venta->load('vehiculo', 'comprador', 'cuotas', 'user');
    }
}; ?>

@php
    $m = fn ($v) => $venta->simboloMoneda().' '.Formato::monto($v);
@endphp

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Venta #{{ $venta->id }} · {{ $venta->vehiculo->descripcion() }}</flux:heading>
            <flux:subheading>{{ $venta->comprador->nombre }} (CI {{ $venta->comprador->ci }}) · {{ $venta->fecha_venta->format('d/m/Y') }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button :href="route('ventas.index')" wire:navigate variant="ghost" icon="arrow-left">Ventas</flux:button>
            <flux:button :href="route('vehiculos.ver', $venta->vehiculo)" wire:navigate icon="truck">Vehículo</flux:button>
        </div>
    </div>

    @if (session('estado'))
        <flux:callout variant="success" icon="check-circle" :heading="session('estado')" />
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card>
            <div class="text-sm text-zinc-500">Precio</div>
            <div class="mt-1 text-2xl font-semibold">{{ $m($venta->precio) }}</div>
            <div class="text-xs text-zinc-500">{{ $venta->tipo === 'credito' ? 'A crédito' : 'Al contado' }}</div>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">Cuota inicial</div>
            <div class="mt-1 text-2xl font-semibold">{{ $m($venta->inicial) }}</div>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">Saldo por cobrar</div>
            <div class="mt-1 text-2xl font-semibold">{{ $m($venta->saldo()) }}</div>
            <div class="text-xs text-zinc-500">{{ $venta->numero_cuotas }} cuota(s)</div>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">Tipo de cambio</div>
            <div class="mt-1 font-semibold">{{ Venta::REGLAS_TC[$venta->regla_tc] }}</div>
            @if ($venta->tc_pactado)
                <div class="text-xs text-zinc-500">Pactado: {{ Formato::monto($venta->tc_pactado) }}</div>
            @endif
        </flux:card>
    </div>

    @if ($venta->cuotas->isNotEmpty())
        <flux:card class="overflow-x-auto">
            <flux:heading size="lg" class="mb-3">Plan de cuotas</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>N.º</flux:table.column>
                    <flux:table.column>Vence</flux:table.column>
                    <flux:table.column align="end">Monto</flux:table.column>
                    <flux:table.column align="end">Pagado</flux:table.column>
                    <flux:table.column align="end">Saldo</flux:table.column>
                    <flux:table.column>Estado</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($venta->cuotas as $cuota)
                        <flux:table.row :key="$cuota->id">
                            <flux:table.cell>{{ $cuota->numero }}</flux:table.cell>
                            <flux:table.cell>{{ $cuota->vencimiento->format('d/m/Y') }}</flux:table.cell>
                            <flux:table.cell align="end">{{ Formato::monto($cuota->monto) }}</flux:table.cell>
                            <flux:table.cell align="end">{{ Formato::monto($cuota->pagado) }}</flux:table.cell>
                            <flux:table.cell align="end" variant="strong">{{ Formato::monto($cuota->saldo()) }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="\App\Models\Cuota::COLORES[$cuota->estado()]">{{ $cuota->nombreEstado() }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</div>
