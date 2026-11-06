<?php

use App\Models\Vehiculo;
use App\Support\Formato;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/**
 * PBI-03: listado de vehículos del negocio.
 * (La búsqueda y el filtro por estado llegan con el PBI-04.)
 */
new #[Title('Vehículos')] class extends Component {
    use WithPagination;

    #[Computed]
    public function vehiculos()
    {
        return Vehiculo::latest('id')->paginate(15);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Vehículos</flux:heading>
            <flux:subheading>Inventario identificado por chasis.</flux:subheading>
        </div>
        @can('vehiculos.gestionar')
            <flux:button variant="primary" icon="plus" :href="route('vehiculos.crear')" wire:navigate>Nuevo vehículo</flux:button>
        @endcan
    </div>

    <flux:card class="overflow-x-auto">
        @if ($this->vehiculos->isEmpty())
            <div class="py-10 text-center text-zinc-500">Todavía no hay vehículos registrados.</div>
        @else
            <flux:table :paginate="$this->vehiculos">
                <flux:table.columns>
                    <flux:table.column>Vehículo</flux:table.column>
                    <flux:table.column>Chasis</flux:table.column>
                    <flux:table.column>Placa</flux:table.column>
                    <flux:table.column align="end">Precio</flux:table.column>
                    <flux:table.column>Estado</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->vehiculos as $vehiculo)
                        <flux:table.row :key="$vehiculo->id">
                            <flux:table.cell variant="strong">
                                <a href="{{ route('vehiculos.ver', $vehiculo) }}" wire:navigate class="hover:underline">{{ $vehiculo->descripcion() }}</a>
                            </flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">{{ $vehiculo->chasis }}</flux:table.cell>
                            <flux:table.cell>{{ $vehiculo->placa ?? '—' }}</flux:table.cell>
                            <flux:table.cell align="end">{{ Formato::usd($vehiculo->precio_usd) }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$vehiculo->colorEstado()">{{ $vehiculo->nombreEstado() }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
