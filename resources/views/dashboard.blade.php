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

    $cobrosHoy = $veCartera
        ? \App\Models\Cobro::whereDate('created_at', today())->whereNull('anulado_at')->whereIn('medio', ['efectivo', 'qr'])->get()
        : collect();

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

    // Alto de cada barra en % del tramo mayor (mínimo visible 6 %).
    $alturas = function (array $valores) {
        $max = max(array_map('floatval', $valores) ?: [0]);

        return array_map(fn ($v) => $max > 0 ? max(6, round(100 * (float) $v / $max)) : 6, $valores);
    };
    $coloresTramo = ['por_vencer' => '#2F6BFF', 'd1_30' => '#FFB630', 'd31_60' => '#FF8A3D', 'd61_mas' => '#FF4D6A'];
    $i = 0;
@endphp

<x-layouts.app title="Panel">
    <div class="flex flex-col gap-6">
        <div class="entrar flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 font-mono text-xs uppercase tracking-[.16em] text-niebla">
                    <span class="punto-vivo"></span>
                    {{ $usuario->negocio?->nombre }} · {{ now()->translatedFormat('l j \d\e F \d\e Y') }}
                </div>
                <h1 class="mt-1 font-display text-2xl tracking-wide">Hola, {{ str($usuario->name)->before(' ') }}</h1>
            </div>
            @can('cobros.registrar')
                <flux:button variant="primary" icon="banknotes" :href="route('cobros.index')" wire:navigate>Registrar cobro</flux:button>
            @endcan
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

        {{-- Tarjetas de datos --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @if ($veCartera)
                @forelse ($cartera as $moneda => $datos)
                    @php $tramos = $datos['tramos']; $h = $alturas(array_values($tramos)); @endphp
                    <div class="vidrio tarjeta-dato entrar {{ $loop->first ? 'destacada' : '' }} flex flex-col gap-2 p-5" style="--i: {{ $i++ }}">
                        <span class="reflejo"></span>
                        <span class="font-mono text-[11px] uppercase tracking-[.16em] text-niebla">Por cobrar ({{ $simbolo($moneda) }})</span>
                        <span class="font-mono text-2xl tabular-nums {{ $loop->first ? 'texto-vivo' : '' }}"
                              data-contar="{{ $datos['total'] }}" data-prefijo="{{ $simbolo($moneda) }} ">{{ $simbolo($moneda) }} {{ Formato::monto($datos['total']) }}</span>
                        <span class="text-xs {{ (float) $datos['vencido'] > 0 ? 'text-mora' : 'text-niebla' }}">Vencido: {{ $simbolo($moneda) }} {{ Formato::monto($datos['vencido']) }}</span>
                        <div class="barras mt-1" title="Por vencer · 1–30 · 31–60 · más de 60 días">
                            @foreach (array_keys($tramos) as $j => $tramo)
                                <span style="--h: {{ $h[$j] }}%; --b: {{ $coloresTramo[$tramo] }}; --j: {{ $j }}"></span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="vidrio tarjeta-dato entrar flex flex-col gap-2 p-5" style="--i: {{ $i++ }}">
                        <span class="font-mono text-[11px] uppercase tracking-[.16em] text-niebla">Por cobrar</span>
                        <span class="font-mono text-2xl">Sin saldos</span>
                        <span class="text-xs text-niebla">Las ventas a crédito aparecerán aquí.</span>
                    </div>
                @endforelse

                <div class="vidrio tarjeta-dato entrar flex flex-col gap-2 p-5" style="--i: {{ $i++ }}">
                    <span class="reflejo"></span>
                    <span class="font-mono text-[11px] uppercase tracking-[.16em] text-niebla">Cuotas vencidas</span>
                    <span class="font-mono text-2xl tabular-nums {{ $vencidas['total'] > 0 ? 'text-mora' : '' }}" data-contar="{{ $vencidas['total'] }}" data-decimales="0">{{ $vencidas['total'] }}</span>
                    <span class="text-xs text-niebla">
                        @can('cartera.ver')
                            <a class="underline decoration-white/30 hover:text-faro" href="{{ route('cartera.index') }}" wire:navigate>Ver cartera</a>
                        @else
                            Desde el día siguiente al vencimiento
                        @endcan
                    </span>
                </div>

                <div class="vidrio tarjeta-dato entrar flex flex-col gap-2 p-5" style="--i: {{ $i++ }}">
                    <span class="reflejo"></span>
                    <span class="font-mono text-[11px] uppercase tracking-[.16em] text-niebla">Cobros de hoy</span>
                    <span class="font-mono text-2xl tabular-nums text-pago" data-contar="{{ $cobrosHoy->count() }}" data-decimales="0">{{ $cobrosHoy->count() }}</span>
                    <span class="text-xs text-niebla">
                        {{ $cobrosHoy->where('estado', 'por_confirmar')->count() }} QR por confirmar
                    </span>
                </div>
            @endif

            @php
                $totalAutos = max(1, $porEstado->sum());
                $hAutos = $alturas([$porEstado['en_exhibicion'] ?? 0, $porEstado['reservado'] ?? 0, $porEstado['vendido'] ?? 0, $porEstado['entregado'] ?? 0]);
            @endphp
            <div class="vidrio tarjeta-dato entrar flex flex-col gap-2 p-5" style="--i: {{ $i++ }}">
                <span class="reflejo"></span>
                <span class="font-mono text-[11px] uppercase tracking-[.16em] text-niebla">En exhibición</span>
                <span class="font-mono text-2xl tabular-nums" data-contar="{{ $porEstado['en_exhibicion'] ?? 0 }}" data-decimales="0">{{ $porEstado['en_exhibicion'] ?? 0 }}</span>
                <span class="text-xs text-niebla">{{ $porEstado['reservado'] ?? 0 }} reservado(s) · {{ $porEstado['vendido'] ?? 0 }} vendido(s) · {{ $porEstado['entregado'] ?? 0 }} entregado(s)</span>
                <div class="barras mt-1" title="Exhibición · reservado · vendido · entregado">
                    @foreach (['#20E3F2', '#FFB630', '#2F6BFF', '#8B5CFF'] as $j => $color)
                        <span style="--h: {{ $hAutos[$j] }}%; --b: {{ $color }}; --j: {{ $j }}"></span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- PBI-09 (CA-09): alerta de cuotas vencidas --}}
        @if ($veCartera && $vencidas['total'] > 0)
            <flux:card class="overflow-x-auto">
                <div class="mb-3 flex items-center gap-2">
                    <flux:icon name="bell-alert" class="text-mora" />
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
                                    <div class="text-xs text-niebla">{{ $cuota->venta->comprador->telefono }}</div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $cuota->venta->vehiculo->descripcion() }}</flux:table.cell>
                                <flux:table.cell>{{ $cuota->numero }} de {{ $cuota->venta->numero_cuotas }}</flux:table.cell>
                                <flux:table.cell>
                                    {{ $cuota->vencimiento->format('d/m/Y') }}
                                    <flux:badge size="sm" color="red">{{ (int) $cuota->vencimiento->diffInDays(today()) }} días</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell align="end" class="font-mono">{{ $cuota->venta->simboloMoneda() }} {{ Formato::monto($cuota->saldo()) }}</flux:table.cell>
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

        {{-- Inventario por estado --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (Vehiculo::ESTADOS as $clave => $nombre)
                <a href="{{ route('vehiculos.index', ['estado' => $clave]) }}" wire:navigate
                   class="vidrio entrar group flex items-center justify-between gap-3 p-4" style="--i: {{ $i++ }}">
                    <span>
                        <span class="block text-sm text-niebla">{{ $nombre }}</span>
                        <span class="font-mono text-2xl tabular-nums" data-contar="{{ $porEstado[$clave] ?? 0 }}" data-decimales="0">{{ $porEstado[$clave] ?? 0 }}</span>
                        <span class="block text-xs text-niebla">vehículos</span>
                    </span>
                    <flux:icon name="arrow-up-right" class="size-5 text-niebla transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-faro" />
                </a>
            @endforeach
        </div>
    </div>
</x-layouts.app>
