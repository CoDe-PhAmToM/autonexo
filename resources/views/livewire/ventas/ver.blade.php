<?php

use App\Models\Cobro;
use App\Models\Venta;
use App\Servicios\Transferencias;
use App\Support\Formato;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/**
 * PBI-06: ficha de la venta con su plan de cuotas.
 * PBI-07: cobros de la venta (anular con motivo, confirmar QR).
 * PBI-10: registrar la entrega, que inicia la transferencia.
 */
new #[Title('Venta')] class extends Component {
    public Venta $venta;

    public ?int $anulandoId = null;
    public string $motivo = '';
    public string $fecha_minuta = '';

    public function mount(): void
    {
        $this->fecha_minuta = today()->toDateString();
    }

    public function with(): array
    {
        $this->venta->load('vehiculo', 'comprador', 'cuotas', 'user', 'transferencia');

        return ['cobros' => $this->venta->cobros()->with('user', 'cuotas')->get()];
    }

    public function empezarAnulacion(int $cobroId): void
    {
        abort_unless(auth()->user()->can('cobros.anular'), 403);
        $this->anulandoId = $cobroId;
        $this->motivo = '';
        $this->resetErrorBag();
    }

    public function anular(): void
    {
        abort_unless(auth()->user()->can('cobros.anular'), 403);
        $this->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']], [], ['motivo' => 'motivo']);

        $this->venta->cobros()->findOrFail($this->anulandoId)->anular($this->motivo);

        $this->anulandoId = null;
        $this->motivo = '';
        session()->flash('estado', 'Cobro anulado; el saldo de las cuotas se recalculó.');
    }

    public function confirmar(int $cobroId): void
    {
        abort_unless(auth()->user()->can('cobros.confirmar'), 403);
        $this->venta->cobros()->findOrFail($cobroId)->confirmar();
        session()->flash('estado', 'Pago por QR confirmado.');
    }

    /** PBI-12: el comprador autoriza (o retira) el envío de comprobantes. Queda en la bitácora. */
    public function cambiarConsentimiento(bool $acepta): void
    {
        abort_unless(auth()->user()->canAny(['ventas.registrar', 'cobros.registrar']), 403);
        $this->venta->comprador->registrarConsentimiento($acepta);
        session()->flash('estado', $acepta ? 'Consentimiento registrado: ya se pueden enviar comprobantes.' : 'Consentimiento retirado.');
    }

    public function registrarEntrega(Transferencias $transferencias): void
    {
        abort_unless(auth()->user()->can('transferencias.gestionar'), 403);
        $this->validate(['fecha_minuta' => ['required', 'date', 'before_or_equal:today']], [], ['fecha_minuta' => 'fecha de la minuta']);

        $transferencia = $transferencias->iniciar($this->venta, $this->fecha_minuta);

        session()->flash('estado', 'Entrega registrada: se creó la lista de control de la transferencia.');
        $this->redirectRoute('transferencias.ver', $transferencia, navigate: true);
    }
}; ?>

@php
    $m = fn ($v) => $venta->simboloMoneda().' '.Formato::monto($v);
@endphp

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Venta #{{ $venta->id }} · {{ $venta->vehiculo->descripcion() }}</flux:heading>
            <flux:subheading>{{ $venta->comprador->nombre }} (CI {{ $venta->comprador->ci }}) · {{ $venta->fecha_venta->format('d/m/Y') }}</flux:subheading>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button :href="route('ventas.index')" wire:navigate variant="ghost" icon="arrow-left">Ventas</flux:button>
            <flux:button :href="route('vehiculos.ver', $venta->vehiculo)" wire:navigate icon="truck">Vehículo</flux:button>
            @if ($venta->transferencia)
                <flux:button :href="route('transferencias.ver', $venta->transferencia)" wire:navigate icon="clipboard-document-check">Transferencia</flux:button>
            @endif
            @can('cobros.registrar')
                @if ($venta->tipo === 'credito' && (float) $venta->saldo() > 0)
                    <flux:button :href="route('ventas.cobrar', $venta)" wire:navigate variant="primary" icon="banknotes">Cobrar</flux:button>
                @endif
            @endcan
        </div>
    </div>

    @if (session('estado'))
        <flux:callout variant="success" icon="check-circle" :heading="session('estado')" />
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card>
            <div class="text-sm text-zinc-500">Precio</div>
            <div class="mt-1 text-2xl font-semibold">{{ $m($venta->precio) }}</div>
            <div class="text-xs text-zinc-500">{{ $venta->tipo === 'credito' ? 'A crédito' : 'Al contado' }}</div>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">Cuota inicial</div>
            <div class="mt-1 text-2xl font-semibold">{{ $m($venta->inicial) }}</div>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">Saldo por cobrar</div>
            <div class="mt-1 text-2xl font-semibold">{{ $m($venta->saldo()) }}</div>
            <div class="text-xs text-zinc-500">{{ $venta->numero_cuotas }} cuota(s) · solo pagos confirmados</div>
        </flux:card>
        <flux:card>
            <div class="text-sm text-zinc-500">Tipo de cambio</div>
            <div class="mt-1 font-semibold">{{ Venta::REGLAS_TC[$venta->regla_tc] }}</div>
            @if ($venta->tc_pactado)
                <div class="text-xs text-zinc-500">Pactado: {{ Formato::monto($venta->tc_pactado) }}</div>
            @endif
        </flux:card>
    </div>

    {{-- PBI-12: comprobantes al comprador, solo con su consentimiento --}}
    <flux:card class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="lg">Comprobantes para el comprador</flux:heading>
            @if ($venta->comprador->consentimiento_comprobante)
                <flux:text class="text-sm">
                    {{ $venta->comprador->nombre }} autorizó recibirlos el {{ $venta->comprador->consentimiento_at?->format('d/m/Y') }}.
                    @unless ($venta->comprador->whatsapp()) Sin teléfono válido para WhatsApp. @endunless
                </flux:text>
            @else
                <flux:text class="text-sm">Pregunta al comprador si autoriza recibir sus comprobantes y estado de cuenta. Sin su consentimiento no se generan.</flux:text>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($venta->comprador->consentimiento_comprobante)
                <flux:button icon="document-arrow-down" :href="route('ventas.estado-cuenta', $venta)" target="_blank">Estado de cuenta (PDF)</flux:button>
                @canany(['ventas.registrar', 'cobros.registrar'])
                    <flux:button variant="ghost" size="sm" wire:click="cambiarConsentimiento(false)" wire:confirm="¿Retirar el consentimiento del comprador?">Retirar consentimiento</flux:button>
                @endcanany
            @else
                @canany(['ventas.registrar', 'cobros.registrar'])
                    <flux:button variant="primary" icon="check" wire:click="cambiarConsentimiento(true)">El comprador autoriza</flux:button>
                @endcanany
            @endif
        </div>
    </flux:card>

    @if (! $venta->transferencia && $venta->vehiculo->estado === 'vendido')
        @can('transferencias.gestionar')
            <flux:card>
                <form wire:submit="registrarEntrega" class="flex flex-wrap items-end gap-4">
                    <div>
                        <flux:heading size="lg">Entrega del vehículo</flux:heading>
                        <flux:text class="text-sm">Al registrar la entrega se crea la lista de control de la transferencia con el plazo del IMT.</flux:text>
                    </div>
                    <flux:input wire:model="fecha_minuta" type="date" label="Fecha de la minuta" />
                    <flux:button type="submit" icon="key">Registrar entrega</flux:button>
                </form>
            </flux:card>
        @endcan
    @endif

    @if ($venta->cuotas->isNotEmpty())
        <flux:card class="overflow-x-auto">
            <flux:heading size="lg" class="mb-3">Plan de cuotas</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>N.º</flux:table.column>
                    <flux:table.column>Vence</flux:table.column>
                    <flux:table.column align="end">Monto</flux:table.column>
                    <flux:table.column align="end">Pagado</flux:table.column>
                    <flux:table.column align="end">Saldo</flux:table.column>
                    <flux:table.column>Estado</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($venta->cuotas as $cuota)
                        <flux:table.row :key="$cuota->id">
                            <flux:table.cell>{{ $cuota->numero }}</flux:table.cell>
                            <flux:table.cell>{{ $cuota->vencimiento->format('d/m/Y') }}</flux:table.cell>
                            <flux:table.cell align="end">{{ Formato::monto($cuota->monto) }}</flux:table.cell>
                            <flux:table.cell align="end">{{ Formato::monto($cuota->pagado) }}</flux:table.cell>
                            <flux:table.cell align="end" variant="strong">{{ Formato::monto($cuota->saldo()) }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="\App\Models\Cuota::COLORES[$cuota->estado()]">{{ $cuota->nombreEstado() }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @if ($cobros->isNotEmpty())
        <flux:card class="overflow-x-auto">
            <flux:heading size="lg" class="mb-3">Cobros</flux:heading>

            @if ($anulandoId)
                <form wire:submit="anular" class="mb-4 flex flex-col gap-3 rounded-lg border border-red-300 p-4 dark:border-red-800">
                    <flux:heading>Anular el cobro #{{ $anulandoId }}</flux:heading>
                    <flux:text class="text-sm">El cobro no se borra: queda anulado en la bitácora con tu nombre, la fecha y el motivo.</flux:text>
                    <flux:textarea wire:model="motivo" label="Motivo" rows="2" required />
                    <div class="flex gap-2">
                        <flux:button type="submit" variant="danger" size="sm">Anular cobro</flux:button>
                        <flux:button type="button" size="sm" variant="ghost" wire:click="$set('anulandoId', null)">Cancelar</flux:button>
                    </div>
                </form>
            @endif

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>N.º</flux:table.column>
                    <flux:table.column>Fecha</flux:table.column>
                    <flux:table.column>Medio</flux:table.column>
                    <flux:table.column align="end">Recibido</flux:table.column>
                    <flux:table.column>TC</flux:table.column>
                    <flux:table.column align="end">Aplicado</flux:table.column>
                    <flux:table.column>Cuotas</flux:table.column>
                    <flux:table.column>Estado</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($cobros as $cobro)
                        <flux:table.row :key="'cobro-'.$cobro->id" class="{{ $cobro->estaAnulado() ? 'opacity-60' : '' }}">
                            <flux:table.cell>#{{ $cobro->id }}</flux:table.cell>
                            <flux:table.cell>
                                {{ $cobro->created_at->format('d/m/Y H:i') }}
                                <div class="text-xs text-zinc-500">{{ $cobro->user?->name }}</div>
                            </flux:table.cell>
                            <flux:table.cell>{{ Cobro::MEDIOS[$cobro->medio] }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $cobro->simboloPago() }} {{ Formato::monto($cobro->monto_pago) }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($cobro->tc_aplicado)
                                    {{ Formato::monto($cobro->tc_aplicado) }}
                                    <div class="text-xs text-zinc-500" title="{{ $cobro->motivo_tc }}">{{ $cobro->fuente_tc }}</div>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $m($cobro->monto_aplicado) }}</flux:table.cell>
                            <flux:table.cell>{{ $cobro->cuotas->pluck('numero')->implode(', ') }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$cobro->colorEstado()">{{ $cobro->nombreEstado() }}</flux:badge>
                                @if ($cobro->estaAnulado())
                                    <div class="max-w-48 whitespace-normal text-xs text-zinc-500">{{ $cobro->motivo_anulacion }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($cobro->comprobante_path)
                                    <flux:button size="xs" variant="ghost" icon="photo" :href="route('cobros.comprobante', $cobro)" target="_blank" title="Captura del QR" />
                                @endif
                                @if ($venta->comprador->consentimiento_comprobante && $cobro->medio !== 'carga_inicial')
                                    <flux:button size="xs" variant="ghost" icon="document-text" :href="route('cobros.pdf', $cobro)" target="_blank" title="Comprobante PDF" />
                                    @if ($wa = app(\App\Servicios\Comprobantes::class)->enlaceWhatsApp($cobro))
                                        <flux:button size="xs" variant="ghost" icon="chat-bubble-left-right" :href="$wa" target="_blank" title="Enviar por WhatsApp" />
                                    @endif
                                @endif
                                @if ($cobro->porConfirmar())
                                    @can('cobros.confirmar')
                                        <flux:button size="xs" variant="primary" wire:click="confirmar({{ $cobro->id }})" wire:confirm="¿Confirmas que el depósito llegó a la cuenta?">Confirmar</flux:button>
                                    @endcan
                                @endif
                                @if (! $cobro->estaAnulado())
                                    @can('cobros.anular')
                                        <flux:button size="xs" variant="ghost" wire:click="empezarAnulacion({{ $cobro->id }})">Anular</flux:button>
                                    @endcan
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</div>
