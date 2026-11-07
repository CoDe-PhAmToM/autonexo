<?php

use App\Servicios\Metricas;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/**
 * PBI-23 (CA-23): medición de uso según el protocolo del Entregable (HP-01 y HP-03).
 */
new #[Title('Medición de uso')] class extends Component {
    public function mount(): void
    {
        abort_unless(auth()->user()->can('metricas.ver'), 403);
    }

    public function with(Metricas $metricas): array
    {
        return ['semanas' => $metricas->semanas(4)];
    }
}; ?>

@php
    $pct = fn ($v) => $v === null ? 'Sin dato' : number_format($v, 1, ',', '.').' %';
    $seg = fn ($v) => $v === null ? 'Sin dato' : number_format($v, 1, ',', '.').' s';
    $actual = $semanas[0];
    $anterior = $semanas[1] ?? null;
    $estado = fn ($v, $meta) => $v === null ? 'zinc' : ($v >= $meta ? 'green' : 'red');
@endphp

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">Medición de uso</flux:heading>
        <flux:subheading>Indicadores del piloto (Entregable, cap. 6). Solo conteos y tiempos: no se guardan datos personales.</flux:subheading>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card>
            <div class="text-sm text-zinc-500">Cobros registrados el mismo día</div>
            <div class="mt-1 text-3xl font-semibold">{{ $pct($actual['mismo_dia']) }}</div>
            <div class="text-xs text-zinc-500">Semana anterior: {{ $pct($anterior['mismo_dia'] ?? null) }}</div>
            <flux:badge size="sm" class="mt-1" :color="$estado($actual['mismo_dia'], Metricas::META_MISMO_DIA)">HP-03 · meta {{ Metricas::META_MISMO_DIA }} %</flux:badge>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">Consultas de cartera esta semana</div>
            <div class="mt-1 text-3xl font-semibold">{{ $actual['consultas'] }}</div>
            <div class="text-xs text-zinc-500">Semana anterior: {{ $anterior['consultas'] ?? 0 }}</div>
            <flux:badge size="sm" class="mt-1" :color="$actual['consultas'] >= Metricas::META_CONSULTAS ? 'green' : 'red'">HP-01 · meta {{ Metricas::META_CONSULTAS }} o más</flux:badge>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">Consultas de cartera en menos de 1 minuto</div>
            <div class="mt-1 text-3xl font-semibold">{{ $pct($actual['consultas_rapidas']) }}</div>
            <div class="text-xs text-zinc-500">Semana anterior: {{ $pct($anterior['consultas_rapidas'] ?? null) }}</div>
            <flux:badge size="sm" class="mt-1" :color="$estado($actual['consultas_rapidas'], 90)">Meta 90 %</flux:badge>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">Tiempo para registrar un cobro (mediana)</div>
            <div class="mt-1 text-3xl font-semibold">{{ $seg($actual['mediana_cobro']) }}</div>
            <div class="text-xs text-zinc-500">Semana anterior: {{ $seg($anterior['mediana_cobro'] ?? null) }}</div>
            <flux:badge size="sm" class="mt-1" :color="$actual['mediana_cobro'] === null ? 'zinc' : ($actual['mediana_cobro'] < Metricas::META_SEGUNDOS ? 'green' : 'red')">Meta menos de 60 s</flux:badge>
        </flux:card>
    </div>

    <flux:card class="overflow-x-auto">
        <flux:heading size="lg" class="mb-3">Últimas 4 semanas</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Semana</flux:table.column>
                <flux:table.column align="end">Cobros</flux:table.column>
                <flux:table.column align="end">Mismo día</flux:table.column>
                <flux:table.column align="end">Consultas de cartera</flux:table.column>
                <flux:table.column align="end">Cartera &lt; 1 min</flux:table.column>
                <flux:table.column align="end">Tiempo de cobro</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($semanas as $s)
                    <flux:table.row>
                        <flux:table.cell>{{ $s['desde']->format('d/m') }} – {{ $s['hasta']->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $s['cobros'] }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $pct($s['mismo_dia']) }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $s['consultas'] }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $pct($s['consultas_rapidas']) }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $seg($s['mediana_cobro']) }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        <flux:text class="mt-3 text-xs">«Sin dato» significa que en esa semana no hubo eventos para medir; no es lo mismo que 0 %.</flux:text>
    </flux:card>
</div>
