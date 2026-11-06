<?php

use App\Models\Vehiculo;
use App\Servicios\Ventas;
use App\Support\Formato;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/**
 * PBI-03 y PBI-04 (CA-04): stock por estado y búsqueda por chasis, placa o modelo.
 * El listado refleja el estado actual: al abrirlo se vencen las reservas pasadas de fecha (CA-05).
 */
new #[Title('Vehículos')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(except: '')]
    public string $estado = '';

    public function mount(Ventas $ventas): void
    {
        $ventas->vencerReservas();
    }

    public function updatedBuscar(): void { $this->resetPage(); }
    public function updatedEstado(): void { $this->resetPage(); }

    #[Computed]
    public function vehiculos()
    {
        $texto = trim($this->buscar);
        $chasis = Vehiculo::normalizarChasis($texto);

        return Vehiculo::query()
            ->when($texto !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('chasis', 'like', "%{$chasis}%")
                ->orWhere('placa', 'like', '%'.mb_strtoupper(str_replace(' ', '', $texto)).'%')
                ->orWhere('modelo', 'ilike', "%{$texto}%")
                ->orWhere('marca', 'ilike', "%{$texto}%")))
            ->when($this->estado !== '', fn ($q) => $q->where('estado', $this->estado))
            ->latest('id')
            ->paginate(15);
    }

    #[Computed]
    public function conteo()
    {
        return Vehiculo::selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Vehículos</flux:heading>
            <flux:subheading>Stock por estado. Busca por chasis, placa, marca o modelo.</flux:subheading>
        </div>
        @can('vehiculos.gestionar')
            <flux:button variant="primary" icon="plus" :href="route('vehiculos.crear')" wire:navigate>Nuevo vehículo</flux:button>
        @endcan
    </div>

    <div class="flex flex-wrap gap-2">
        <flux:button size="sm" :variant="$estado === '' ? 'primary' : 'outline'" wire:click="$set('estado', '')">
            Todos · {{ $this->conteo->sum() }}
        </flux:button>
        @foreach (Vehiculo::ESTADOS as $clave => $nombre)
            <flux:button size="sm" :variant="$estado === $clave ? 'primary' : 'outline'" wire:click="$set('estado', '{{ $clave }}')">
                {{ $nombre }} · {{ $this->conteo[$clave] ?? 0 }}
            </flux:button>
        @endforeach
    </div>

    <flux:card class="flex flex-col gap-4 overflow-x-auto">
        <flux:input wire:model.live.debounce.300ms="buscar" icon="magnifying-glass" placeholder="Chasis, placa, marca o modelo…" clearable />

        @if ($this->vehiculos->isEmpty())
            <div class="py-10 text-center text-zinc-500">
                {{ $buscar !== '' || $estado !== '' ? 'Ningún vehículo coincide con la búsqueda.' : 'Todavía no hay vehículos registrados.' }}
            </div>
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
