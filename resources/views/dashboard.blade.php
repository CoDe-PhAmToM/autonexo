@php
    use App\Models\Vehiculo;
    use App\Servicios\Cartera;
    use App\Support\Formato;

    $usuario = auth()->user();
    app(\App\Servicios\Ventas::class)->vencerReservas(); // CA-05: el panel refleja el estado actual

    $porEstado = Vehiculo::query()
        ->selectRaw('estado, count(*) as total')
        ->groupBy('estado')
        ->pluck('total', 'estado');

    // PBI-08 y PBI-09: total por cobrar y cuotas vencidas (calculado al abrir el panel, sin tareas programadas).
    $veCartera = $usuario->canAny(['cartera.ver', 'cobros.registrar']);
    $cartera = $veCartera ? collect(app(Cartera::class)->resumen($usuario->negocio_id))->sortKeysDesc() : collect();
    $vencidas = $veCartera ? app(Cartera::class)->cuotasVencidas() : ['total' => 0, 'cuotas' => collect()];

    // CA-10.2: transferencias con el pago del IMT pendiente y el plazo cerca (o vencido).
    $alertasImt = $usuario->can('transferencias.gestionar')
        ? \App\Models\Transferencia::with('pasos', 'venta.vehiculo', 'venta.comprador')
            ->where('estado', 'en_curso')->get()->filter->alertaImt()
        : collect();

    // PBI-22 (pivote HP-03): recordatorio de cerrar la caja si hubo cobros hoy.
    $cobrosSinCerrar = $usuario->can('caja.cerrar')
        && ! \App\Models\CierreCaja::where('user_id', $usuario->id)->whereDate('fecha', today())->exists()
        ? \App\Models\Cobro::where('user_id', $usuario->id)->whereDate('created_at', today())->whereNull('anulado_at')->whereIn('medio', ['efectivo', 'qr'])->count()
        : 0;

    $simbolo = fn ($m) => $m === 'BOB' ? 'Bs' : 'USD';
@endphp

<x-layouts.app title="Panel">
    <div class="flex flex-col gap-6">
        <div>
            <flux:heading size="xl">Hola, {{ str($usuario->name)->before(' ') }}</flux:heading>
            <flux:subheading>{{ $usuario->negocio?->nombre }} · {{ now()->translatedFormat('l j \d\e F \d\e Y') }}</flux:subheading>
        </div>

        @if ($cobrosSinCerrar > 0)
            <flux:callout icon="lock-open" color="amber">
                <flux:callout.heading>Todavía no cerraste la caja de hoy</flux:callout.heading>
                <flux:callout.text>
                    Registraste {{ $cobrosSinCerrar }} cobro(s). Al terminar el día, cuenta el efectivo y los QR y
                    <a class="underline" href="{{ route('caja.index') }}" wire:navigate>cierra la caja</a>.
                </flux:callout.text>
            </flux:callout>
        @endif

        @foreach ($alertasImt as $t)
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:callout.heading>
                    IMT: {{ $t->textoPlazoImt() }}
                    · límite {{ $t->fecha_limite_imt->format('d/m/Y') }}
                </flux:callout.heading>
                <flux:callout.text>
                    {{ $t->venta->vehiculo->descripcion() }} ({{ $t->venta->vehiculo->placa ?? 'sin placa' }}) · {{ $t->venta->comprador->nombre }} · paso pendiente: Pago del IMT.
                    <a class="underline" href="{{ route('transferencias.ver', $t) }}" wire:navigate>Ver transferencia</a>
                </flux:callout.text>
            </flux:callout>
        @endforeach

        @if ($veCartera)
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @forelse ($cartera as $moneda => $datos)
                    <flux:card>
                        <div class="text-sm text-zinc-500">Por cobrar ({{ $simbolo($moneda) }})</div>
                        <div class="mt-1 text-2xl font-semibold">{{ $simbolo($moneda) }} {{ Formato::monto($datos['total']) }}</div>
                        <div class="text-xs {{ (float) $datos['vencido'] > 0 ? 'text-red-500' : 'text-zinc-500' }}">Vencido: {{ $simbolo($moneda) }} {{ Formato::monto($datos['vencido']) }}</div>
                    </flux:card>
                @empty
                    <flux:card>
                        <div class="text-sm text-zinc-500">Por cobrar</div>
                        <div class="mt-1 text-2xl font-semibold">Sin saldos</div>
                    </flux:card>
                @endforelse
                <flux:card>
                    <div class="text-sm text-zinc-500">Cuotas vencidas</div>
                    <div class="mt-1 text-2xl font-semibold {{ $vencidas['total'] > 0 ? 'text-red-500' : '' }}">{{ $vencidas['total'] }}</div>
                    @can('cartera.ver')
                        <a class="text-xs underline" href="{{ route('cartera.index') }}" wire:navigate>Ver cartera</a>
                    @endcan
                </flux:card>
            </div>

            {{-- PBI-09 (CA-09): alerta de cuotas vencidas --}}
            @if ($vencidas['total'] > 0)
                <flux:card class="overflow-x-auto">
                    <div class="mb-3 flex items-center gap-2">
                        <flux:icon name="bell-alert" class="text-red-500" />
                        <flux:heading size="lg">Cuotas vencidas ({{ $vencidas['total'] }})</flux:heading>
                    </div>
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Comprador</flux:table.column>
                            <flux:table.column>Vehículo</flux:table.column>
                            <flux:table.column>Cuota</flux:table.column>
                            <flux:table.column>Venció</flux:table.column>
                            <flux:table.column align="end">Saldo</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($vencidas['cuotas'] as $cuota)
                                <flux:table.row :key="'venc-'.$cuota->id">
                                    <flux:table.cell variant="strong">
                                        {{ $cuota->venta->comprador->nombre }}
                                        <div class="text-xs text-zinc-500">{{ $cuota->venta->comprador->telefono }}</div>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $cuota->venta->vehiculo->descripcion() }}</flux:table.cell>
                                    <flux:table.cell>{{ $cuota->numero }} de {{ $cuota->venta->numero_cuotas }}</flux:table.cell>
                                    <flux:table.cell>
                                        {{ $cuota->vencimiento->format('d/m/Y') }}
                                        <flux:badge size="sm" color="red">{{ (int) $cuota->vencimiento->diffInDays(today()) }} días</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell align="end">{{ $cuota->venta->simboloMoneda() }} {{ Formato::monto($cuota->saldo()) }}</flux:table.cell>
                                    <flux:table.cell align="end">
                                        @can('cobros.registrar')
                                            <flux:button size="xs" variant="primary" :href="route('ventas.cobrar', $cuota->venta)" wire:navigate>Cobrar</flux:button>
                                        @else
                                            <flux:button size="xs" :href="route('ventas.ver', $cuota->venta)" wire:navigate>Ver</flux:button>
                                        @endcan
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                    @if ($vencidas['total'] > $vencidas['cuotas']->count())
                        <flux:text class="mt-2 text-sm">Se muestran las {{ $vencidas['cuotas']->count() }} más antiguas.</flux:text>
                    @endif
                </flux:card>
            @endif
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (Vehiculo::ESTADOS as $clave => $nombre)
                <flux:card>
                    <div class="text-sm text-zinc-500">{{ $nombre }}</div>
                    <div class="mt-1 text-3xl font-semibold">{{ $porEstado[$clave] ?? 0 }}</div>
                    <div class="text-xs text-zinc-500">vehículos</div>
                </flux:card>
            @endforeach
        </div>
    </div>
</x-layouts.app>
