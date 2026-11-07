<?php

use App\Servicios\CargaInicial;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * PBI-13 (CA-13): carga inicial desde la plantilla de Excel.
 */
new #[Title('Carga inicial')] class extends Component {
    use WithFileUploads;

    public $archivo = null;
    public ?array $resultado = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('datos.importar'), 403);
    }

    public function importar(CargaInicial $carga): void
    {
        abort_unless(auth()->user()->can('datos.importar'), 403);

        $this->validate(
            ['archivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120']],
            ['archivo.mimes' => 'Sube la plantilla en formato Excel (.xlsx).'],
            ['archivo' => 'archivo'],
        );

        $this->resultado = $carga->procesar($this->archivo->getRealPath());
        $this->reset('archivo');
    }
}; ?>

<div class="flex max-w-4xl flex-col gap-6">
    <div>
        <flux:heading size="xl">Carga inicial</flux:heading>
        <flux:subheading>Sube tu stock y tus ventas a crédito vigentes desde la plantilla, sin copiar todo a mano.</flux:subheading>
    </div>

    <flux:card class="flex flex-col gap-4">
        <ol class="list-decimal space-y-1 pl-5 text-sm">
            <li>Descarga la plantilla. Tiene dos hojas: <strong>Vehiculos</strong> y <strong>Ventas a credito</strong>, con una fila de ejemplo.</li>
            <li>Reemplaza el ejemplo con tus datos. Montos con punto decimal (9500.00); fechas como 2026-06-05 o 05/06/2026.</li>
            <li>En ventas, <em>cuotas_pagadas</em> es cuántas cuotas ya te pagaron antes de usar AutoNexo.</li>
            <li>Sube el archivo. Las filas con errores se informan y las correctas se guardan igual.</li>
        </ol>
        <div>
            <flux:button :href="route('carga.plantilla')" icon="arrow-down-tray">Descargar plantilla</flux:button>
        </div>

        <form wire:submit="importar" class="flex flex-wrap items-end gap-3">
            <div class="min-w-64 flex-1">
                <flux:input wire:model="archivo" type="file" accept=".xlsx,.xls" label="Archivo de Excel" />
            </div>
            <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled">Cargar</flux:button>
        </form>
    </flux:card>

    @if ($resultado)
        <div class="grid gap-4 sm:grid-cols-3">
            <flux:card>
                <div class="text-sm text-zinc-500">Vehículos cargados</div>
                <div class="mt-1 font-mono text-3xl tabular-nums text-pago">{{ $resultado['vehiculos'] }}</div>
            </flux:card>
            <flux:card>
                <div class="text-sm text-zinc-500">Ventas cargadas</div>
                <div class="mt-1 font-mono text-3xl tabular-nums text-pago">{{ $resultado['ventas'] }}</div>
            </flux:card>
            <flux:card>
                <div class="text-sm text-zinc-500">Filas con errores</div>
                <div class="mt-1 font-mono text-3xl tabular-nums {{ count($resultado['errores']) ? 'text-mora' : '' }}">
                    {{ collect($resultado['errores'])->unique(fn ($e) => $e['hoja'].$e['fila'])->count() }}
                </div>
            </flux:card>
        </div>

        @if ($resultado['errores'])
            <flux:card class="overflow-x-auto">
                <flux:heading size="lg" class="mb-3">Filas que no se cargaron</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Hoja</flux:table.column>
                        <flux:table.column>Fila</flux:table.column>
                        <flux:table.column>Problema</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($resultado['errores'] as $error)
                            <flux:table.row>
                                <flux:table.cell>{{ $error['hoja'] }}</flux:table.cell>
                                <flux:table.cell>{{ $error['fila'] }}</flux:table.cell>
                                <flux:table.cell class="whitespace-normal">{{ $error['mensaje'] }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @endif
    @endif
</div>
