<?php

use App\Models\Respaldo;
use App\Servicios\Respaldos;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/**
 * PBI-21 (CA-21): respaldo, estado de los respaldos (los fallidos visibles)
 * y exportación de los registros del negocio (solo autorizada).
 */
new #[Title('Respaldos')] class extends Component {
    public function mount(): void
    {
        abort_unless(auth()->user()->can('respaldos.gestionar'), 403);
    }

    #[Computed]
    public function respaldos()
    {
        return Respaldo::with('user')->latest('id')->limit(15)->get();
    }

    public function generar(Respaldos $respaldos): void
    {
        abort_unless(auth()->user()->can('respaldos.gestionar'), 403);
        $resultado = $respaldos->generar();
        unset($this->respaldos);

        session()->flash($resultado->exitoso() ? 'exito' : 'fallo', $resultado->exitoso()
            ? "Respaldo generado: {$resultado->archivo}"
            : 'El respaldo falló: '.$resultado->mensaje);
    }
}; ?>

<div class="flex max-w-4xl flex-col gap-6">
    <div>
        <flux:heading size="xl">Respaldos</flux:heading>
        <flux:subheading>Copia de la base de datos y de las fotos y comprobantes, para no perder información.</flux:subheading>
    </div>

    @if (session('exito'))
        <flux:callout variant="success" icon="check-circle" :heading="session('exito')" />
    @endif
    @if (session('fallo'))
        <flux:callout variant="danger" icon="x-circle" :heading="session('fallo')" />
    @endif

    @php $ultimo = $this->respaldos->first(); @endphp
    @if ($ultimo && ! $ultimo->exitoso())
        <flux:callout variant="danger" icon="exclamation-triangle" heading="El último respaldo falló ({{ $ultimo->created_at->format('d/m/Y H:i') }})">
            <flux:callout.text>{{ $ultimo->mensaje }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <flux:card class="flex flex-col gap-3">
            <flux:heading size="lg">Respaldo completo</flux:heading>
            <flux:text class="text-sm">Se genera solo cada noche a las 23:30 y también a mano con este botón. Queda guardado en el servidor.</flux:text>
            <div>
                <flux:button variant="primary" icon="circle-stack" wire:click="generar" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="generar">Generar respaldo ahora</span>
                    <span wire:loading wire:target="generar">Generando…</span>
                </flux:button>
            </div>
        </flux:card>
        <flux:card class="flex flex-col gap-3">
            <flux:heading size="lg">Exportar mis registros</flux:heading>
            <flux:text class="text-sm">Excel con vehículos, ventas, cuotas, cobros y repuestos de {{ auth()->user()->negocio->nombre }}. Solo lo puede descargar el dueño.</flux:text>
            <div>
                <flux:button icon="arrow-down-tray" :href="route('respaldos.exportar')">Descargar Excel</flux:button>
            </div>
        </flux:card>
    </div>

    <flux:card class="overflow-x-auto">
        <flux:heading size="lg" class="mb-3">Últimos respaldos</flux:heading>
        @if ($this->respaldos->isEmpty())
            <flux:text>Todavía no se generó ningún respaldo.</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Fecha</flux:table.column>
                    <flux:table.column>Resultado</flux:table.column>
                    <flux:table.column>Archivo</flux:table.column>
                    <flux:table.column align="end">Tamaño</flux:table.column>
                    <flux:table.column>Generó</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->respaldos as $r)
                        <flux:table.row :key="'resp-'.$r->id">
                            <flux:table.cell>{{ $r->created_at->format('d/m/Y H:i') }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$r->exitoso() ? 'green' : 'red'">{{ $r->exitoso() ? 'Exitoso' : 'Fallido' }}</flux:badge></flux:table.cell>
                            <flux:table.cell class="whitespace-normal text-xs">{{ $r->archivo ?? $r->mensaje }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $r->tamano() }}</flux:table.cell>
                            <flux:table.cell>{{ $r->user?->name ?? 'Automático' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    <flux:card>
        <flux:heading size="lg" class="mb-2">Cómo se restaura</flux:heading>
        <flux:text class="text-sm">
            El procedimiento está en <code>RESPALDO.md</code>. Para comprobar que el último respaldo sirve, ejecuta
            <code>php artisan autonexo:probar-restauracion</code>: lo restaura en una base limpia y compara los conteos y saldos con la base actual.
        </flux:text>
    </flux:card>
</div>
