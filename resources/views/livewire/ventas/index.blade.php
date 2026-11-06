<?php

use App\Models\Venta;
use App\Support\Formato;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

/** PBI-06: listado de ventas con el saldo por cobrar de cada una. */
new #[Title('Ventas')] class extends Component {
    use WithPagination;

    public string $buscar = '';

    public function updatedBuscar(): void { $this->resetPage(); }

    #[Computed]
    public function ventas()
    {
        $texto = trim($this->buscar);

        return Venta::with('vehiculo', 'comprador', 'cuotas')
            ->when($texto !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('comprador', fn ($c) => $c->where('nombre', 'ilike', "%{$texto}%")->orWhere('ci', 'ilike', "%{$texto}%"))
                ->orWhereHas('vehiculo', fn ($v) => $v->where('chasis', 'ilike', "%{$texto}%")->orWhere('placa', 'ilike', "%{$texto}%")->orWhere('modelo', 'ilike', "%{$texto}%"))))
            ->latest('id')
            ->paginate(15);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">Ventas</flux:heading>
        <flux:subheading>Ventas registradas y saldo por cobrar. Para vender, abre un vehículo y usa «Vender».</flux:subheading>
    </div>

    <flux:card class="flex flex-col gap-4 overflow-x-auto">
        <flux:input wire:model.live.debounce.300ms="buscar" icon="magnifying-glass" placeholder="Comprador, CI, chasis, placa o modelo…" />

        @if ($this->ventas->isEmpty())
            <div class="py-10 text-center text-zinc-500">No hay ventas que mostrar.</div>
        @else
            <flux:table :paginate="$this->ventas">
                <flux:table.columns>
                    <flux:table.column>N.º</flux:table.column>
                    <flux:table.column>Fecha</flux:table.column>
                    <flux:table.column>Comprador</flux:table.column>
                    <flux:table.column>Vehículo</flux:table.column>
                    <flux:table.column>Tipo</flux:table.column>
                    <flux:table.column align="end">Precio</flux:table.column>
                    <flux:table.column align="end">Saldo</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->ventas as $venta)
                        <flux:table.row :key="$venta->id">
                            <flux:table.cell>
                                <a href="{{ route('ventas.ver', $venta) }}" wire:navigate class="font-medium hover:underline">#{{ $venta->id }}</a>
                            </flux:table.cell>
                            <flux:table.cell>{{ $venta->fecha_venta->format('d/m/Y') }}</flux:table.cell>
                            <flux:table.cell variant="strong">{{ $venta->comprador->nombre }}</flux:table.cell>
                            <flux:table.cell>{{ $venta->vehiculo->descripcion() }}</flux:table.cell>
                            <flux:table.cell>{{ $venta->tipo === 'credito' ? 'Crédito' : 'Contado' }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $venta->simboloMoneda() }} {{ Formato::monto($venta->precio) }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $venta->simboloMoneda() }} {{ Formato::monto($venta->saldo()) }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
