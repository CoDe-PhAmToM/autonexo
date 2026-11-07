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

    /** vitrina (tarjetas con foto) | tabla */
    #[Url(except: 'vitrina')]
    public string $vista = 'vitrina';

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
            ->with('fotos')
            ->when($texto !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('chasis', 'like', "%{$chasis}%")
                ->orWhere('placa', 'like', '%'.mb_strtoupper(str_replace(' ', '', $texto)).'%')
                ->orWhere('modelo', 'ilike', "%{$texto}%")
                ->orWhere('marca', 'ilike', "%{$texto}%")))
            ->when($this->estado !== '', fn ($q) => $q->where('estado', $this->estado))
            ->latest('id')
            ->paginate(12);
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
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex rounded-full border border-white/10 bg-noche/40 p-1" role="group" aria-label="Forma de ver">
                <button type="button" wire:click="$set('vista', 'vitrina')" @class(['flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm transition', 'bg-white/10 text-cromo shadow-[inset_0_0_0_1px_rgba(32,227,242,.4)]' => $vista === 'vitrina', 'text-niebla hover:text-cromo' => $vista !== 'vitrina'])>
                    <flux:icon name="squares-2x2" class="size-4" /> Vitrina
                </button>
                <button type="button" wire:click="$set('vista', 'tabla')" @class(['flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm transition', 'bg-white/10 text-cromo shadow-[inset_0_0_0_1px_rgba(32,227,242,.4)]' => $vista === 'tabla', 'text-niebla hover:text-cromo' => $vista !== 'tabla'])>
                    <flux:icon name="table-cells" class="size-4" /> Tabla
                </button>
            </div>
            @can('vehiculos.gestionar')
                <flux:button variant="primary" icon="plus" :href="route('vehiculos.crear')" wire:navigate>Nuevo vehículo</flux:button>
            @endcan
        </div>
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
        @elseif ($vista === 'vitrina')
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($this->vehiculos as $vehiculo)
                    <a href="{{ route('vehiculos.ver', $vehiculo) }}" wire:navigate wire:key="v-{{ $vehiculo->id }}"
                       class="vidrio entrar group flex flex-col overflow-hidden transition duration-300 hover:-translate-y-1 hover:border-faro/40 hover:shadow-[0_24px_50px_-20px_rgba(32,227,242,.45)]">
                        <div class="relative aspect-[16/10] overflow-hidden">
                            @if ($vehiculo->portada())
                                <img src="{{ $vehiculo->portada()->url() }}" alt="{{ $vehiculo->descripcion() }}" loading="lazy"
                                     class="size-full object-cover transition duration-700 group-hover:scale-105">
                            @else
                                <x-silueta-auto class="size-full" :etiqueta="false" />
                            @endif
                            <span class="pointer-events-none absolute inset-0 bg-gradient-to-t from-noche/90 via-transparent to-transparent"></span>
                            <span class="absolute left-3 top-3 rounded-full bg-noche/70 backdrop-blur-sm"><flux:badge size="sm" :color="$vehiculo->colorEstado()">{{ $vehiculo->nombreEstado() }}</flux:badge></span>
                            @if ($vehiculo->fotos->count() > 1)
                                <span class="absolute right-3 top-3 flex items-center gap-1 rounded-full bg-noche/70 px-2 py-0.5 font-mono text-[11px] text-cromo">
                                    <flux:icon name="photo" class="size-3.5" /> {{ $vehiculo->fotos->count() }}
                                </span>
                            @endif
                            <span class="absolute bottom-2 left-3 font-mono text-lg text-cromo drop-shadow">{{ Formato::usd($vehiculo->precio_usd) }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-2 p-4">
                            <div class="min-w-0">
                                <div class="truncate font-medium text-cromo group-hover:text-faro">{{ $vehiculo->marca }} {{ $vehiculo->modelo }}</div>
                                <div class="truncate font-mono text-xs text-niebla">{{ $vehiculo->anio }} · {{ $vehiculo->placa ?? 'sin placa' }} · {{ $vehiculo->color ?? '—' }}</div>
                            </div>
                            <flux:icon name="arrow-up-right" class="size-5 shrink-0 text-niebla transition group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-faro" />
                        </div>
                    </a>
                @endforeach
            </div>
            <flux:pagination :paginator="$this->vehiculos" />
        @else
            <flux:table :paginate="$this->vehiculos">
                <flux:table.columns>
                    <flux:table.column>Vehículo</flux:table.column>
                    <flux:table.column class="max-sm:hidden">Chasis</flux:table.column>
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
                            <flux:table.cell class="font-mono text-xs max-sm:hidden">{{ $vehiculo->chasis }}</flux:table.cell>
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
