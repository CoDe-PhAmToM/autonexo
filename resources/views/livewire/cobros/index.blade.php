<?php

use App\Models\Cobro;
use App\Models\TipoCambio;
use App\Models\Venta;
use App\Support\Formato;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/**
 * PBI-07: caja del cajero. Tipo de cambio del día, buscar la venta a cobrar,
 * cobros de hoy y QR por confirmar.
 */
new #[Title('Cobros')] class extends Component {
    public string $buscar = '';
    public string $tc_valor = '';
    public string $tc_fuente = 'Oficial';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('cobros.registrar'), 403);
    }

    #[Computed]
    public function tipoCambio(): ?TipoCambio
    {
        return TipoCambio::vigente();
    }

    #[Computed]
    public function ventas()
    {
        $texto = trim($this->buscar);
        if (mb_strlen($texto) < 2) {
            return collect();
        }

        return Venta::with('vehiculo', 'comprador', 'cuotas')
            ->where('tipo', 'credito')
            ->where(fn ($q) => $q
                ->whereHas('comprador', fn ($c) => $c->where('nombre', 'ilike', "%{$texto}%")->orWhere('ci', 'ilike', "%{$texto}%"))
                ->orWhereHas('vehiculo', fn ($v) => $v->where('placa', 'ilike', "%{$texto}%")->orWhere('chasis', 'ilike', "%{$texto}%")))
            ->latest('id')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function cobrosDeHoy()
    {
        return Cobro::with('venta.comprador', 'user')->whereDate('created_at', today())->latest('id')->get();
    }

    #[Computed]
    public function porConfirmar()
    {
        return Cobro::with('venta.comprador', 'user')->where('estado', 'por_confirmar')->whereNull('anulado_at')->oldest('id')->get();
    }

    public function registrarTc(): void
    {
        abort_unless(auth()->user()->can('cobros.registrar'), 403);

        $this->validate([
            'tc_valor' => ['required', 'regex:/^\d{1,4}(\.\d{1,4})?$/'],
            'tc_fuente' => ['required', 'string', 'max:60'],
        ], ['tc_valor.regex' => 'Escribe el tipo de cambio con punto y hasta 4 decimales (ej. 12.26).'], ['tc_valor' => 'tipo de cambio', 'tc_fuente' => 'fuente']);

        if (TipoCambio::whereDate('fecha', today())->exists()) {
            $this->addError('tc_valor', 'El tipo de cambio de hoy ya está registrado. Si un cobro necesita otro, cámbialo en el cobro con su motivo.');
            return;
        }

        TipoCambio::create(['fecha' => today(), 'valor' => $this->tc_valor, 'fuente' => $this->tc_fuente, 'user_id' => auth()->id()]);
        $this->reset('tc_valor');
        unset($this->tipoCambio);
    }

    public function confirmar(int $cobroId): void
    {
        abort_unless(auth()->user()->can('cobros.confirmar'), 403);
        Cobro::findOrFail($cobroId)->confirmar();
        unset($this->porConfirmar, $this->cobrosDeHoy);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">Cobros</flux:heading>
        <flux:subheading>Busca la venta, registra el cobro y revisa la caja del día.</flux:subheading>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card>
            <flux:heading size="lg">Tipo de cambio</flux:heading>
            @if ($this->tipoCambio)
                <div class="mt-2 font-mono text-3xl tabular-nums texto-vivo">Bs {{ Formato::monto($this->tipoCambio->valor) }}</div>
                <div class="text-sm text-zinc-500">por 1 USD · {{ $this->tipoCambio->fuente }} del {{ $this->tipoCambio->fecha->format('d/m/Y') }}</div>
            @else
                <div class="mt-2 text-sm text-zinc-500">Todavía no hay tipo de cambio registrado.</div>
            @endif

            @if (! $this->tipoCambio?->esDeHoy())
                <form wire:submit="registrarTc" class="mt-4 flex flex-col gap-3">
                    <flux:text class="text-sm">Registra el de hoy:</flux:text>
                    <div class="grid grid-cols-2 gap-2">
                        <flux:input wire:model="tc_valor" placeholder="12.26" inputmode="decimal" />
                        <flux:input wire:model="tc_fuente" placeholder="Fuente" />
                    </div>
                    <flux:error name="tc_valor" />
                    <flux:button type="submit" size="sm" variant="primary">Guardar tipo de cambio</flux:button>
                </form>
            @endif
        </flux:card>

        <flux:card class="lg:col-span-2 flex flex-col gap-3">
            <flux:heading size="lg">Nuevo cobro</flux:heading>
            <flux:input wire:model.live.debounce.300ms="buscar" icon="magnifying-glass" placeholder="Nombre o CI del comprador, placa o chasis…" />
            @foreach ($this->ventas as $venta)
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <div>
                        <div class="font-medium">{{ $venta->comprador->nombre }} <span class="text-zinc-500">· CI {{ $venta->comprador->ci }}</span></div>
                        <div class="text-sm text-zinc-500">{{ $venta->vehiculo->descripcion() }} · {{ $venta->vehiculo->placa }} · saldo {{ $venta->simboloMoneda() }} {{ Formato::monto($venta->saldo()) }}</div>
                    </div>
                    <flux:button size="sm" variant="primary" icon="banknotes" :href="route('ventas.cobrar', $venta)" wire:navigate>Cobrar</flux:button>
                </div>
            @endforeach
            @if (mb_strlen(trim($buscar)) >= 2 && $this->ventas->isEmpty())
                <flux:text>No hay ventas a crédito que coincidan.</flux:text>
            @endif
        </flux:card>
    </div>

    @if ($this->porConfirmar->isNotEmpty())
        <flux:card class="overflow-x-auto">
            <flux:heading size="lg" class="mb-3">QR por confirmar ({{ $this->porConfirmar->count() }})</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Fecha</flux:table.column>
                    <flux:table.column>Comprador</flux:table.column>
                    <flux:table.column align="end">Monto</flux:table.column>
                    <flux:table.column>Registró</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->porConfirmar as $cobro)
                        <flux:table.row :key="'qr-'.$cobro->id">
                            <flux:table.cell>{{ $cobro->created_at->format('d/m/Y H:i') }}</flux:table.cell>
                            <flux:table.cell>
                                <a class="hover:underline" href="{{ route('ventas.ver', $cobro->venta) }}" wire:navigate>{{ $cobro->venta->comprador->nombre }}</a>
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $cobro->simboloPago() }} {{ Formato::monto($cobro->monto_pago) }}</flux:table.cell>
                            <flux:table.cell>{{ $cobro->user?->name }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="xs" variant="ghost" icon="photo" :href="route('cobros.comprobante', $cobro)" target="_blank">Captura</flux:button>
                                @can('cobros.confirmar')
                                    <flux:button size="xs" variant="primary" wire:click="confirmar({{ $cobro->id }})" wire:confirm="¿Confirmas que el depósito llegó a la cuenta?">Confirmar</flux:button>
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    <flux:card class="overflow-x-auto">
        <flux:heading size="lg" class="mb-3">Cobros de hoy</flux:heading>
        @if ($this->cobrosDeHoy->isEmpty())
            <flux:text>Todavía no hay cobros hoy.</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Hora</flux:table.column>
                    <flux:table.column>Comprador</flux:table.column>
                    <flux:table.column>Medio</flux:table.column>
                    <flux:table.column align="end">Recibido</flux:table.column>
                    <flux:table.column align="end">Aplicado</flux:table.column>
                    <flux:table.column>Estado</flux:table.column>
                    <flux:table.column>Cajero</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->cobrosDeHoy as $cobro)
                        <flux:table.row :key="'hoy-'.$cobro->id">
                            <flux:table.cell>{{ $cobro->created_at->format('H:i') }}</flux:table.cell>
                            <flux:table.cell>
                                <a class="hover:underline" href="{{ route('ventas.ver', $cobro->venta) }}" wire:navigate>{{ $cobro->venta->comprador->nombre }}</a>
                            </flux:table.cell>
                            <flux:table.cell>{{ Cobro::MEDIOS[$cobro->medio] }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $cobro->simboloPago() }} {{ Formato::monto($cobro->monto_pago) }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $cobro->venta->simboloMoneda() }} {{ Formato::monto($cobro->monto_aplicado) }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$cobro->colorEstado()">{{ $cobro->nombreEstado() }}</flux:badge></flux:table.cell>
                            <flux:table.cell>{{ $cobro->user?->name }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
