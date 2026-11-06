<?php

use App\Models\Repuesto;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/**
 * PBI-11 (CA-11): registrar entradas y salidas de un repuesto y ver su kárdex.
 */
new #[Title('Kárdex')] class extends Component {
    public Repuesto $repuesto;

    public string $tipo = 'salida';
    public string $cantidad = '1';
    public string $detalle = '';

    public function registrar(): void
    {
        $this->validate([
            'tipo' => ['required', Rule::in(['entrada', 'salida'])],
            'cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'detalle' => ['nullable', 'string', 'max:255'],
        ]);

        $this->repuesto->mover($this->tipo, (int) $this->cantidad, $this->detalle ?: null);

        $this->reset('detalle');
        $this->cantidad = '1';
        $this->dispatch('movimiento-registrado');
    }

    public function with(): array
    {
        return ['movimientos' => $this->repuesto->movimientos()->with('user')->limit(100)->get()];
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $repuesto->nombre }}</flux:heading>
            <flux:subheading class="font-mono">{{ $repuesto->codigo }}</flux:subheading>
        </div>
        <flux:button :href="route('repuestos.index')" wire:navigate variant="ghost" icon="arrow-left">Volver</flux:button>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <flux:card>
            <div class="text-sm text-zinc-500">Stock actual</div>
            <div class="mt-1 text-3xl font-semibold {{ $repuesto->bajoMinimo() ? 'text-red-500' : '' }}">{{ $repuesto->stock }}</div>
            <div class="text-xs text-zinc-500">{{ $repuesto->unidad }}(s)</div>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">Stock mínimo</div>
            <div class="mt-1 text-3xl font-semibold">{{ $repuesto->stock_minimo }}</div>
            @if ($repuesto->bajoMinimo())
                <flux:badge size="sm" color="red" class="mt-1">Bajo el mínimo: reponer</flux:badge>
            @endif
        </flux:card>
        <flux:card>
            <form wire:submit="registrar" class="flex flex-col gap-3">
                <flux:radio.group wire:model="tipo" variant="segmented" size="sm">
                    <flux:radio value="entrada" label="Entrada" />
                    <flux:radio value="salida" label="Salida" />
                </flux:radio.group>
                <div class="grid grid-cols-3 gap-2">
                    <flux:input wire:model="cantidad" type="number" min="1" placeholder="Cant." />
                    <div class="col-span-2">
                        <flux:input wire:model="detalle" placeholder="Detalle (opcional)" />
                    </div>
                </div>
                <flux:error name="cantidad" />
                <div class="flex items-center gap-3">
                    <flux:button type="submit" variant="primary" size="sm">Registrar</flux:button>
                    <x-action-message on="movimiento-registrado">Registrado.</x-action-message>
                </div>
            </form>
        </flux:card>
    </div>

    <flux:card class="overflow-x-auto">
        <flux:heading size="lg" class="mb-3">Kárdex</flux:heading>
        @if ($movimientos->isEmpty())
            <div class="py-6 text-center text-zinc-500">Sin movimientos.</div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Fecha</flux:table.column>
                    <flux:table.column>Movimiento</flux:table.column>
                    <flux:table.column align="end">Cantidad</flux:table.column>
                    <flux:table.column align="end">Saldo</flux:table.column>
                    <flux:table.column>Detalle</flux:table.column>
                    <flux:table.column>Usuario</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($movimientos as $mov)
                        <flux:table.row :key="$mov->id">
                            <flux:table.cell>{{ $mov->created_at->format('d/m/Y H:i') }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$mov->tipo === 'entrada' ? 'green' : 'orange'">{{ ucfirst($mov->tipo) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $mov->tipo === 'entrada' ? '+' : '−' }}{{ $mov->cantidad }}</flux:table.cell>
                            <flux:table.cell align="end" variant="strong">{{ $mov->saldo }}</flux:table.cell>
                            <flux:table.cell>{{ $mov->detalle ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $mov->user?->name ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
