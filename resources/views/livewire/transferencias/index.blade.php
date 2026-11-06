<?php

use App\Models\Transferencia;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/** PBI-10: transferencias en curso y concluidas, con la alerta del plazo del IMT (CA-10.2). */
new #[Title('Transferencias')] class extends Component {
    public string $estado = 'en_curso';

    #[Computed]
    public function transferencias()
    {
        return Transferencia::with('venta.vehiculo', 'venta.comprador', 'pasos')
            ->when($this->estado !== '', fn ($q) => $q->where('estado', $this->estado))
            ->orderBy('fecha_limite_imt')
            ->get();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">Transferencias</flux:heading>
        <flux:subheading>Para no quedar como dueño de un auto que ya vendiste. Se crean al registrar la entrega en la venta.</flux:subheading>
    </div>

    <div class="flex gap-2">
        <flux:button size="sm" :variant="$estado === 'en_curso' ? 'primary' : 'outline'" wire:click="$set('estado', 'en_curso')">En curso</flux:button>
        <flux:button size="sm" :variant="$estado === 'concluida' ? 'primary' : 'outline'" wire:click="$set('estado', 'concluida')">Concluidas</flux:button>
        <flux:button size="sm" :variant="$estado === '' ? 'primary' : 'outline'" wire:click="$set('estado', '')">Todas</flux:button>
    </div>

    <flux:card class="overflow-x-auto">
        @if ($this->transferencias->isEmpty())
            <div class="py-10 text-center text-zinc-500">No hay transferencias que mostrar.</div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Vehículo</flux:table.column>
                    <flux:table.column>Comprador</flux:table.column>
                    <flux:table.column>Avance</flux:table.column>
                    <flux:table.column>Límite del IMT</flux:table.column>
                    <flux:table.column>Estado</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->transferencias as $t)
                        <flux:table.row :key="$t->id">
                            <flux:table.cell variant="strong">
                                <a class="hover:underline" href="{{ route('transferencias.ver', $t) }}" wire:navigate>{{ $t->venta->vehiculo->descripcion() }}</a>
                                <div class="text-xs text-zinc-500">{{ $t->venta->vehiculo->placa }}</div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $t->venta->comprador->nombre }}</flux:table.cell>
                            <flux:table.cell>{{ $t->pasos->filter->cumplido()->count() }} de {{ $t->pasos->count() }}</flux:table.cell>
                            <flux:table.cell>
                                {{ $t->fecha_limite_imt->format('d/m/Y') }}
                                @if ($t->alertaImt())
                                    <flux:badge size="sm" color="red">
                                        {{ ucfirst($t->textoPlazoImt()) }}
                                    </flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$t->concluida() ? 'green' : 'blue'">{{ $t->concluida() ? 'Concluida' : 'En curso' }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
