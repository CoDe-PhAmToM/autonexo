<?php

use App\Models\Repuesto;
use App\Support\Formato;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * PBI-11 (CA-11): repuestos con su stock; los que están bajo el mínimo se marcan.
 */
new #[Title('Repuestos')] class extends Component {
    use WithFileUploads, WithPagination;

    public string $buscar = '';
    public bool $soloBajoMinimo = false;

    public string $codigo = '';
    public string $nombre = '';
    public string $unidad = 'pieza';
    public string $stock_minimo = '0';
    public string $precio_bs = '';
    public string $stock_inicial = '0';
    public $foto = null;

    public function updatedBuscar(): void { $this->resetPage(); }
    public function updatedSoloBajoMinimo(): void { $this->resetPage(); }

    #[Computed]
    public function repuestos()
    {
        return Repuesto::query()
            ->when($this->buscar !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('codigo', 'ilike', "%{$this->buscar}%")
                ->orWhere('nombre', 'ilike', "%{$this->buscar}%")))
            ->when($this->soloBajoMinimo, fn ($q) => $q->where('stock_minimo', '>', 0)->whereColumn('stock', '<', 'stock_minimo'))
            ->orderBy('nombre')
            ->paginate(15);
    }

    #[Computed]
    public function cantidadBajoMinimo(): int
    {
        return Repuesto::where('stock_minimo', '>', 0)->whereColumn('stock', '<', 'stock_minimo')->count();
    }

    public function crear(): void
    {
        $this->codigo = mb_strtoupper(trim($this->codigo));

        $datos = $this->validate([
            'codigo' => ['required', 'string', 'max:30', Rule::unique('repuestos')->where('negocio_id', auth()->user()->negocio_id)],
            'nombre' => ['required', 'string', 'max:255'],
            'unidad' => ['required', 'string', 'max:20'],
            'stock_minimo' => ['required', 'integer', 'min:0'],
            'precio_bs' => ['nullable', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'stock_inicial' => ['required', 'integer', 'min:0'],
            'foto' => ['nullable', 'image', 'max:4096'],
        ], ['codigo.unique' => 'Ya existe un repuesto con este código.'], ['codigo' => 'código', 'stock_minimo' => 'stock mínimo', 'precio_bs' => 'precio']);

        $repuesto = Repuesto::create([
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'],
            'unidad' => $datos['unidad'],
            'stock_minimo' => (int) $datos['stock_minimo'],
            'precio_bs' => $datos['precio_bs'] ?: null,
            'foto_path' => $this->foto ? \App\Support\Imagenes::guardar($this->foto, 'repuestos/'.auth()->user()->negocio_id, 1000) : null,
        ]);

        if ((int) $datos['stock_inicial'] > 0) {
            $repuesto->mover('entrada', (int) $datos['stock_inicial'], 'Stock inicial');
        }

        $this->reset('codigo', 'nombre', 'precio_bs', 'foto');
        $this->unidad = 'pieza';
        $this->stock_minimo = '0';
        $this->stock_inicial = '0';
        unset($this->repuestos, $this->cantidadBajoMinimo);
        $this->dispatch('repuesto-creado');
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">Repuestos</flux:heading>
        <flux:subheading>Kárdex de entradas y salidas. El stock nunca queda en negativo.</flux:subheading>
    </div>

    @if ($this->cantidadBajoMinimo > 0)
        <flux:callout variant="warning" icon="exclamation-triangle"
            heading="{{ $this->cantidadBajoMinimo }} {{ $this->cantidadBajoMinimo === 1 ? 'repuesto está' : 'repuestos están' }} bajo el stock mínimo" />
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="lg:col-span-1">
            <flux:heading size="lg" class="mb-4">Nuevo repuesto</flux:heading>
            <form wire:submit="crear" class="flex flex-col gap-4">
                <flux:input wire:model="codigo" label="Código" required placeholder="FIL-ACE-01" />
                <flux:input wire:model="nombre" label="Nombre" required placeholder="Filtro de aceite Toyota" />
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="unidad" label="Unidad" required />
                    <flux:input wire:model="precio_bs" label="Precio (Bs)" inputmode="decimal" />
                    <flux:input wire:model="stock_inicial" label="Stock inicial" type="number" min="0" />
                    <flux:input wire:model="stock_minimo" label="Stock mínimo" type="number" min="0" />
                </div>
                <flux:input wire:model="foto" type="file" accept="image/*" label="Foto (opcional)" />
                <div class="flex items-center gap-3">
                    <flux:button type="submit" variant="primary">Crear repuesto</flux:button>
                    <x-action-message on="repuesto-creado">Creado.</x-action-message>
                </div>
            </form>
        </flux:card>

        <flux:card class="lg:col-span-2 flex flex-col gap-4 overflow-x-auto">
            <div class="flex flex-wrap items-center gap-4">
                <div class="min-w-48 flex-1">
                    <flux:input wire:model.live.debounce.300ms="buscar" icon="magnifying-glass" placeholder="Buscar por código o nombre" />
                </div>
                <flux:checkbox wire:model.live="soloBajoMinimo" label="Solo bajo el mínimo" />
            </div>

            @if ($this->repuestos->isEmpty())
                <div class="py-10 text-center text-zinc-500">No hay repuestos que mostrar.</div>
            @else
                <flux:table :paginate="$this->repuestos">
                    <flux:table.columns>
                        <flux:table.column class="w-14"></flux:table.column>
                        <flux:table.column>Código</flux:table.column>
                        <flux:table.column>Repuesto</flux:table.column>
                        <flux:table.column align="end">Stock</flux:table.column>
                        <flux:table.column align="end">Mínimo</flux:table.column>
                        <flux:table.column align="end">Precio</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->repuestos as $repuesto)
                            <flux:table.row :key="$repuesto->id">
                                <flux:table.cell>
                                    <a href="{{ route('repuestos.ver', $repuesto) }}" wire:navigate class="block size-10 overflow-hidden rounded-lg border border-white/10">
                                        @if ($repuesto->foto_path)
                                            <img src="{{ route('repuestos.foto', $repuesto) }}" alt="" loading="lazy" class="size-full object-cover">
                                        @else
                                            <x-silueta-repuesto class="size-full" />
                                        @endif
                                    </a>
                                </flux:table.cell>
                                <flux:table.cell class="font-mono text-xs">{{ $repuesto->codigo }}</flux:table.cell>
                                <flux:table.cell variant="strong">
                                    <a href="{{ route('repuestos.ver', $repuesto) }}" wire:navigate class="hover:underline">{{ $repuesto->nombre }}</a>
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    @if ($repuesto->bajoMinimo())
                                        <flux:badge size="sm" color="red">{{ $repuesto->stock }}</flux:badge>
                                    @else
                                        {{ $repuesto->stock }}
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end">{{ $repuesto->stock_minimo }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $repuesto->precio_bs ? Formato::bs($repuesto->precio_bs) : '—' }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    </div>
</div>
