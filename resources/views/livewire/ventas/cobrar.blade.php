<?php

use App\Models\TipoCambio;
use App\Models\Venta;
use App\Servicios\Cobros;
use App\Support\Formato;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * PBI-07 (CA-07.1 a 07.3): cobrar cuotas en Bs o USD, en efectivo o por QR.
 */
new #[Title('Cobrar')] class extends Component {
    use WithFileUploads;

    public Venta $venta;

    public string $medio = 'efectivo';
    public string $moneda_pago = 'BOB';
    public string $monto = '';
    public string $tc = '';
    public string $motivo_tc = '';
    public $comprobante = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('cobros.registrar'), 403);
        $this->venta->load('vehiculo', 'comprador', 'cuotas');
        $this->tc = (string) (TipoCambio::vigente()?->valor ?? '');
    }

    #[Computed]
    public function tipoCambio(): ?TipoCambio
    {
        return TipoCambio::vigente();
    }

    #[Computed]
    public function necesitaTc(): bool
    {
        return $this->moneda_pago !== $this->venta->moneda && $this->venta->regla_tc === 'oficial';
    }

    #[Computed]
    public function tcModificado(): bool
    {
        if (! $this->necesitaTc || ! $this->tipoCambio || $this->tc === '') {
            return false;
        }
        try {
            return \App\Servicios\CalculadoraDePlan::aDiezmilesimos($this->tc)
                !== \App\Servicios\CalculadoraDePlan::aDiezmilesimos((string) $this->tipoCambio->valor);
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    #[Computed]
    public function vistaPrevia(): ?array
    {
        if (! preg_match('/^\d{1,12}(\.\d{1,2})?$/', $this->monto) || (float) $this->monto <= 0) {
            return null;
        }
        try {
            return app(Cobros::class)->simular($this->venta, $this->datos());
        } catch (\Throwable) {
            return null;
        }
    }

    public function cobrar(Cobros $cobros): void
    {
        abort_unless(auth()->user()->can('cobros.registrar'), 403);

        $this->validate([
            'medio' => ['required', Rule::in(['efectivo', 'qr'])],
            'moneda_pago' => ['required', Rule::in(['USD', 'BOB'])],
            'monto' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'tc' => ['nullable', 'regex:/^\d{1,4}(\.\d{1,4})?$/'],
            'motivo_tc' => ['nullable', 'string', 'max:255'],
            'comprobante' => [Rule::requiredIf($this->medio === 'qr'), 'nullable', 'image', 'max:4096'],
        ], [
            'monto.regex' => 'Escribe el monto con punto y hasta 2 decimales (ej. 6130.00).',
            'comprobante.required' => 'Adjunta la captura del pago por QR.',
        ], ['tc' => 'tipo de cambio', 'comprobante' => 'captura del QR']);

        $datos = $this->datos();
        if ($this->comprobante) {
            $datos['comprobante_path'] = $this->comprobante->store('comprobantes/'.auth()->user()->negocio_id, 'local');
        }

        $cobro = $cobros->registrar($this->venta, $datos);

        session()->flash('estado', $cobro->estado === 'por_confirmar'
            ? 'Cobro por QR registrado: queda por confirmar.'
            : 'Cobro registrado.');
        $this->redirectRoute('ventas.ver', $this->venta, navigate: true);
    }

    private function datos(): array
    {
        return [
            'medio' => $this->medio,
            'moneda_pago' => $this->moneda_pago,
            'monto' => $this->monto,
            'tc' => $this->necesitaTc ? $this->tc : null,
            'motivo_tc' => $this->motivo_tc,
        ];
    }
}; ?>

@php
    $sim = $venta->simboloMoneda();
@endphp

<div class="flex max-w-4xl flex-col gap-6">
    <div>
        <flux:heading size="xl">Cobrar · Venta #{{ $venta->id }}</flux:heading>
        <flux:subheading>{{ $venta->comprador->nombre }} · {{ $venta->vehiculo->descripcion() }} · saldo {{ $sim }} {{ Formato::monto($venta->saldo()) }}</flux:subheading>
    </div>

    @if ($this->necesitaTc && ! $this->tipoCambio)
        <flux:callout variant="warning" icon="exclamation-triangle" heading="No hay tipo de cambio registrado">
            <flux:callout.text>Regístralo en <a class="underline" href="{{ route('cobros.index') }}" wire:navigate>Cobros</a> antes de cobrar en otra moneda.</flux:callout.text>
        </flux:callout>
    @elseif ($this->necesitaTc && ! $this->tipoCambio->esDeHoy())
        <flux:callout variant="warning" icon="exclamation-triangle"
            heading="El tipo de cambio de hoy no está registrado; se usa el del {{ $this->tipoCambio->fecha->format('d/m/Y') }}." />
    @endif

    <div class="grid gap-6 lg:grid-cols-5">
        <flux:card class="lg:col-span-3">
            <form wire:submit="cobrar" class="flex flex-col gap-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:radio.group wire:model.live="medio" label="Medio" variant="segmented">
                        <flux:radio value="efectivo" label="Efectivo" />
                        <flux:radio value="qr" label="QR" />
                    </flux:radio.group>
                    <flux:radio.group wire:model.live="moneda_pago" label="Moneda del pago" variant="segmented">
                        <flux:radio value="BOB" label="Bolivianos" />
                        <flux:radio value="USD" label="Dólares" />
                    </flux:radio.group>
                </div>

                <flux:input wire:model.live.debounce.400ms="monto" label="Monto recibido ({{ $moneda_pago === 'BOB' ? 'Bs' : 'USD' }})" inputmode="decimal" required placeholder="6130.00" />

                @if ($this->necesitaTc)
                    <flux:input wire:model.live.debounce.400ms="tc" label="Tipo de cambio (Bs por USD)" inputmode="decimal"
                        description="Precargado con el oficial; cambiarlo exige motivo." />
                    @if ($this->tcModificado)
                        <flux:input wire:model="motivo_tc" label="Motivo del cambio de tipo de cambio" required />
                    @endif
                @elseif ($moneda_pago !== $venta->moneda)
                    <flux:text>Se usa el tipo de cambio pactado en la venta: {{ Formato::monto($venta->tc_pactado) }}.</flux:text>
                @endif
                <flux:error name="tc" />
                <flux:error name="motivo_tc" />

                @if ($medio === 'qr')
                    <flux:input wire:model="comprobante" type="file" accept="image/*" label="Captura del pago por QR" />
                    <flux:text class="text-sm">El cobro queda «por confirmar» hasta que el dueño verifique el depósito.</flux:text>
                @endif

                <div class="flex gap-3">
                    <flux:button type="submit" variant="primary" icon="check">Registrar cobro</flux:button>
                    <flux:button :href="route('ventas.ver', $venta)" wire:navigate variant="ghost">Cancelar</flux:button>
                </div>
            </form>
        </flux:card>

        <flux:card class="lg:col-span-2">
            <flux:heading size="lg" class="mb-3">Cómo se aplica</flux:heading>
            @if ($p = $this->vistaPrevia)
                <div class="text-sm text-zinc-500">Equivale a</div>
                <div class="text-2xl font-semibold">{{ $sim }} {{ Formato::monto($p['aplicado']) }}</div>
                @if ($p['tc'])
                    <div class="text-xs text-zinc-500">TC {{ Formato::monto($p['tc']) }} · {{ $p['fuente'] }}</div>
                @endif
                <ul class="mt-4 flex flex-col gap-2 text-sm">
                    @foreach ($p['reparto'] as $r)
                        <li class="flex justify-between">
                            <span>Cuota {{ $r['numero'] }}</span>
                            <span>{{ Formato::monto($r['monto']) }}
                                <flux:badge size="sm" :color="$r['completa'] ? 'green' : 'amber'">{{ $r['completa'] ? 'Pagada' : 'Parcial' }}</flux:badge>
                            </span>
                        </li>
                    @endforeach
                </ul>
                @if ((float) $p['excedente'] > 0)
                    <flux:callout variant="danger" class="mt-3" icon="x-circle" heading="Supera el saldo por {{ $sim }} {{ Formato::monto($p['excedente']) }}" />
                @endif
            @else
                <flux:text>Escribe el monto para ver a qué cuotas se aplica.</flux:text>
            @endif
        </flux:card>
    </div>
</div>
