<?php

use App\Models\Transferencia;
use App\Servicios\Transferencias;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/** PBI-10 (CA-10.1 a 10.3): lista de control de la transferencia. */
new #[Title('Transferencia')] class extends Component {
    public Transferencia $transferencia;

    /** @var array<int, array{fecha:string, responsable:string}> */
    public array $formulario = [];

    public function mount(): void
    {
        foreach ($this->transferencia->pasos as $paso) {
            $this->formulario[$paso->id] = [
                'fecha' => $paso->fecha?->toDateString() ?? today()->toDateString(),
                'responsable' => (string) $paso->responsable,
            ];
        }
    }

    public function marcar(int $pasoId, Transferencias $transferencias): void
    {
        abort_unless(auth()->user()->can('transferencias.gestionar'), 403);

        $this->validate([
            "formulario.{$pasoId}.fecha" => ['required', 'date', 'before_or_equal:today'],
            "formulario.{$pasoId}.responsable" => ['required', 'string', 'max:120'],
        ], [], ["formulario.{$pasoId}.fecha" => 'fecha', "formulario.{$pasoId}.responsable" => 'responsable']);

        $paso = $this->transferencia->pasos()->findOrFail($pasoId);
        $transferencias->marcarPaso($paso, $this->formulario[$pasoId]['fecha'], $this->formulario[$pasoId]['responsable']);

        $this->transferencia->refresh();
        if ($this->transferencia->concluida()) {
            session()->flash('estado', 'Transferencia concluida: el vehículo pasó a «Entregado».');
        }
    }

    public function with(): array
    {
        $this->transferencia->load('pasos', 'venta.vehiculo', 'venta.comprador');

        return [];
    }
}; ?>

<div class="flex max-w-4xl flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Transferencia · {{ $transferencia->venta->vehiculo->descripcion() }}</flux:heading>
            <flux:subheading>
                {{ $transferencia->venta->comprador->nombre }} (CI {{ $transferencia->venta->comprador->ci }})
                · placa {{ $transferencia->venta->vehiculo->placa ?? '—' }}
            </flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button :href="route('transferencias.index')" wire:navigate variant="ghost" icon="arrow-left">Transferencias</flux:button>
            <flux:button :href="route('ventas.ver', $transferencia->venta)" wire:navigate icon="document-text">Venta</flux:button>
        </div>
    </div>

    @if (session('estado'))
        <flux:callout variant="success" icon="check-circle" :heading="session('estado')" />
    @endif

    @if ($transferencia->alertaImt())
        <flux:callout variant="danger" icon="exclamation-triangle"
            heading="{{ ucfirst($transferencia->textoPlazoImt()) }} · límite del IMT: {{ $transferencia->fecha_limite_imt->format('d/m/Y') }}">
            <flux:callout.text>Paso pendiente: Pago del IMT · {{ $transferencia->venta->vehiculo->descripcion() }} · {{ $transferencia->venta->comprador->nombre }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <flux:card>
            <div class="text-sm text-zinc-500">Minuta</div>
            <div class="mt-1 font-semibold">{{ $transferencia->fecha_minuta->format('d/m/Y') }}</div>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">Límite del IMT</div>
            <div class="mt-1 font-semibold">{{ $transferencia->fecha_limite_imt->format('d/m/Y') }}</div>
            <div class="text-xs text-zinc-500">{{ $transferencia->venta->negocio->plazo_imt_dias ?? 10 }} días hábiles desde la minuta</div>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">Estado</div>
            <flux:badge class="mt-1" :color="$transferencia->concluida() ? 'green' : 'blue'">{{ $transferencia->concluida() ? 'Concluida' : 'En curso' }}</flux:badge>
        </flux:card>
    </div>

    <flux:card>
        <flux:heading size="lg" class="mb-4">Lista de control</flux:heading>
        <ol class="flex flex-col gap-4">
            @foreach ($transferencia->pasos as $paso)
                <li class="grid gap-3 border-b border-zinc-200 pb-4 last:border-0 sm:grid-cols-[minmax(0,1fr)_10rem_12rem_auto] sm:items-center dark:border-zinc-700" wire:key="paso-{{ $paso->id }}">
                    <div>
                        <div class="flex items-center gap-2 font-medium">
                            <flux:icon :name="$paso->cumplido() ? 'check-circle' : 'clock'" variant="mini" class="{{ $paso->cumplido() ? 'text-green-500' : 'text-zinc-400' }}" />
                            {{ $paso->orden }}. {{ $paso->nombre }}
                        </div>
                        @if ($paso->cumplido())
                            <div class="text-sm text-zinc-500">{{ $paso->fecha->format('d/m/Y') }} · {{ $paso->responsable }}</div>
                        @endif
                    </div>
                    @if (! $paso->cumplido() && ! $transferencia->concluida())
                        @can('transferencias.gestionar')
                            <flux:input wire:model="formulario.{{ $paso->id }}.fecha" type="date" size="sm" />
                            <flux:input wire:model="formulario.{{ $paso->id }}.responsable" placeholder="Responsable" size="sm" />
                            <flux:button size="sm" variant="primary" wire:click="marcar({{ $paso->id }})">Marcar</flux:button>
                        @endcan
                    @endif
                </li>
            @endforeach
        </ol>
        <flux:error name="formulario.*" />
    </flux:card>
</div>
