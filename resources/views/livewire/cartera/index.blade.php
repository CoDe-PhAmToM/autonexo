<?php

use App\Servicios\Cartera;
use App\Support\Formato;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/**
 * PBI-08 (CA-08): cartera por cobrar, tramos de antigüedad, compradores en mora
 * y QR por confirmar aparte.
 */
new #[Title('Cartera')] class extends Component {
    public function mount(): void
    {
        abort_unless(auth()->user()->can('cartera.ver'), 403);
    }

    public function with(Cartera $cartera): array
    {
        $negocio = auth()->user()->negocio_id;

        return [
            'resumen' => collect($cartera->resumen($negocio))->sortKeysDesc(), // USD primero
            'mora' => $cartera->enMora($negocio),
            'qr' => $cartera->qrPorConfirmar(),
        ];
    }
}; ?>

@php
    $simbolo = fn ($moneda) => $moneda === 'BOB' ? 'Bs' : 'USD';
    $colorTramo = ['por_vencer' => 'text-zinc-500', 'd1_30' => 'text-amber-500', 'd31_60' => 'text-orange-500', 'd61_mas' => 'text-red-500'];
@endphp

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">Cartera</flux:heading>
        <flux:subheading>Saldos pendientes confirmados al {{ today()->format('d/m/Y') }}, por antigüedad de la mora.</flux:subheading>
    </div>

    @forelse ($resumen as $moneda => $datos)
        <div class="flex flex-col gap-3">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                <flux:card class="lg:col-span-2">
                    <div class="text-sm text-zinc-500">Total por cobrar ({{ $simbolo($moneda) }})</div>
                    <div class="mt-1 text-3xl font-semibold">{{ $simbolo($moneda) }} {{ Formato::monto($datos['total']) }}</div>
                    <div class="text-xs text-zinc-500">{{ $datos['cuotas'] }} cuotas con saldo · vencido {{ $simbolo($moneda) }} {{ Formato::monto($datos['vencido']) }}</div>
                </flux:card>
                @foreach (Cartera::TRAMOS as $clave => $nombre)
                    <flux:card>
                        <div class="text-sm text-zinc-500">{{ $nombre }}</div>
                        <div class="mt-1 text-xl font-semibold {{ (float) $datos['tramos'][$clave] > 0 ? $colorTramo[$clave] : '' }}">
                            {{ Formato::monto($datos['tramos'][$clave]) }}
                        </div>
                    </flux:card>
                @endforeach
            </div>
        </div>
    @empty
        <flux:card><flux:text>No hay saldos pendientes.</flux:text></flux:card>
    @endforelse

    @if ($qr->isNotEmpty())
        <flux:callout icon="qr-code" color="amber">
            <flux:callout.heading>{{ $qr->count() }} pago(s) por QR sin confirmar: no se descuentan de la cartera</flux:callout.heading>
            <flux:callout.text>
                @foreach ($qr as $cobro)
                    <div>{{ $cobro->venta->comprador->nombre }} · {{ $cobro->simboloPago() }} {{ Formato::monto($cobro->monto_pago) }} · {{ $cobro->created_at->format('d/m/Y') }}</div>
                @endforeach
            </flux:callout.text>
        </flux:callout>
    @endif

    <flux:card class="overflow-x-auto">
        <flux:heading size="lg" class="mb-3">Compradores en mora ({{ $mora->count() }})</flux:heading>
        @if ($mora->isEmpty())
            <flux:text>Nadie tiene cuotas vencidas.</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Comprador</flux:table.column>
                    <flux:table.column>Teléfono</flux:table.column>
                    <flux:table.column>Vehículo</flux:table.column>
                    <flux:table.column align="end">Cuotas vencidas</flux:table.column>
                    <flux:table.column align="end">Saldo vencido</flux:table.column>
                    <flux:table.column align="end">Atraso</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($mora as $fila)
                        <flux:table.row :key="'mora-'.$fila->venta_id">
                            <flux:table.cell variant="strong">
                                <a class="hover:underline" href="{{ route('ventas.ver', $fila->venta_id) }}" wire:navigate>{{ $fila->nombre }}</a>
                                <div class="text-xs text-zinc-500">CI {{ $fila->ci }}</div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $fila->telefono ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $fila->marca }} {{ $fila->modelo }} {{ $fila->anio }} <span class="text-zinc-500">{{ $fila->placa }}</span></flux:table.cell>
                            <flux:table.cell align="end">{{ $fila->cuotas_vencidas }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $simbolo($fila->moneda) }} {{ Formato::monto((string) $fila->saldo_vencido) }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:badge size="sm" :color="$fila->dias_atraso > 60 ? 'red' : ($fila->dias_atraso > 30 ? 'orange' : 'amber')">{{ $fila->dias_atraso }} días</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
