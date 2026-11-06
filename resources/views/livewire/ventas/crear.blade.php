<?php

use App\Models\Comprador;
use App\Models\Vehiculo;
use App\Servicios\CalculadoraDePlan;
use App\Servicios\Ventas;
use App\Support\Formato;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/**
 * PBI-06 (CA-06.1 a 06.3): venta al contado o a crédito con plan de cuotas.
 */
new #[Title('Vender')] class extends Component {
    public Vehiculo $vehiculo;

    public string $ci = '';
    public string $nombre = '';
    public string $telefono = '';

    public string $tipo = 'credito';
    public string $moneda = 'USD';
    public string $regla_tc = 'oficial';
    public string $tc_pactado = '';
    public string $precio = '';
    public string $inicial = '';
    public string $numero_cuotas = '12';
    public string $monto_cuota = '';
    public string $primer_vencimiento = '';
    public string $fecha_venta = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('ventas.registrar'), 403);

        $this->precio = (string) $this->vehiculo->precio_usd;
        $this->fecha_venta = today()->toDateString();
        $this->primer_vencimiento = today()->addMonthNoOverflow()->toDateString();

        if ($reserva = $this->vehiculo->reservaActiva()->with('comprador')->first()) {
            $this->ci = $reserva->comprador->ci;
            $this->nombre = $reserva->comprador->nombre;
            $this->telefono = (string) $reserva->comprador->telefono;
            if ($reserva->moneda === $this->moneda) {
                $this->inicial = (string) $reserva->anticipo;
            }
        }
    }

    public function updatedCi(): void
    {
        $comprador = Comprador::where('ci', Ventas::normalizarCi($this->ci))->first();
        if ($comprador) {
            $this->nombre = $comprador->nombre;
            $this->telefono = (string) $comprador->telefono;
        }
    }

    /** Sugiere la cuota con la calculadora del PBI-01. */
    public function calcularCuota(): void
    {
        $this->resetErrorBag();
        try {
            $plan = (new CalculadoraDePlan)->generarCuotas($this->precio, $this->inicial ?: '0', (int) $this->numero_cuotas, now());
            $this->monto_cuota = $plan[0]['monto'];
        } catch (\InvalidArgumentException $e) {
            $this->addError('monto_cuota', $e->getMessage());
        }
    }

    /** Vista previa del plan cuando la cuota escrita cuadra con el precio. */
    #[Computed]
    public function plan(): array
    {
        try {
            $plan = (new CalculadoraDePlan)->generarCuotas(
                $this->precio, $this->inicial ?: '0', (int) $this->numero_cuotas,
                \Carbon\CarbonImmutable::parse($this->primer_vencimiento),
            );
        } catch (\Throwable) {
            return [];
        }

        return $this->monto_cuota !== '' && $plan[0]['monto'] === $this->monto_cuota ? $plan : [];
    }

    public function vender(Ventas $ventas): void
    {
        abort_unless(auth()->user()->can('ventas.registrar'), 403);

        $monto = ['regex:/^\d{1,12}(\.\d{1,2})?$/'];
        $credito = $this->tipo === 'credito';

        $this->validate([
            'ci' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'tipo' => ['required', Rule::in(['contado', 'credito'])],
            'moneda' => ['required', Rule::in(array_keys(\App\Models\Venta::MONEDAS))],
            'regla_tc' => ['required', Rule::in(array_keys(\App\Models\Venta::REGLAS_TC))],
            'tc_pactado' => ['nullable', 'regex:/^\d{1,4}(\.\d{1,4})?$/', Rule::requiredIf($this->moneda === 'BOB' || $this->regla_tc === 'pactado')],
            'precio' => ['required', ...$monto],
            'inicial' => [Rule::requiredIf($credito), ...$monto],
            'numero_cuotas' => [Rule::requiredIf($credito), 'integer', 'between:1,120'],
            'monto_cuota' => [Rule::requiredIf($credito), ...$monto],
            'primer_vencimiento' => [Rule::requiredIf($credito), 'date', 'after_or_equal:fecha_venta'],
            'fecha_venta' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'regex' => 'Escribe el monto con punto y hasta 2 decimales (ej. 9500.00).',
            'tc_pactado.regex' => 'Escribe el tipo de cambio con punto y hasta 4 decimales (ej. 12.26).',
            'tc_pactado.required' => 'Indica el tipo de cambio pactado.',
        ], [
            'ci' => 'CI', 'tc_pactado' => 'tipo de cambio', 'numero_cuotas' => 'número de cuotas',
            'monto_cuota' => 'cuota', 'primer_vencimiento' => 'primer vencimiento', 'fecha_venta' => 'fecha de venta',
        ]);

        $venta = $ventas->vender($this->vehiculo, [
            'comprador' => ['ci' => $this->ci, 'nombre' => $this->nombre, 'telefono' => $this->telefono ?: null],
            'tipo' => $this->tipo,
            'moneda' => $this->moneda,
            'regla_tc' => $this->regla_tc,
            'tc_pactado' => $this->tc_pactado,
            'precio' => $this->precio,
            'inicial' => $this->inicial,
            'numero_cuotas' => $this->numero_cuotas,
            'monto_cuota' => $this->monto_cuota,
            'primer_vencimiento' => $this->primer_vencimiento,
            'fecha_venta' => $this->fecha_venta,
        ]);

        session()->flash('estado', 'Venta registrada.');
        $this->redirectRoute('ventas.ver', $venta, navigate: true);
    }
}; ?>

<div class="flex max-w-4xl flex-col gap-6">
    <div>
        <flux:heading size="xl">Vender {{ $vehiculo->descripcion() }}</flux:heading>
        <flux:subheading>Chasis {{ $vehiculo->chasis }} · {{ $vehiculo->nombreEstado() }} · precio de lista {{ Formato::usd($vehiculo->precio_usd) }}</flux:subheading>
    </div>

    @error('vehiculo')
        <flux:callout variant="danger" icon="x-circle" :heading="$message" />
    @enderror

    <form wire:submit="vender" class="flex flex-col gap-6">
        <flux:card class="grid gap-4 sm:grid-cols-3">
            <flux:heading size="lg" class="sm:col-span-3">Comprador</flux:heading>
            <flux:input wire:model.blur="ci" label="CI" required />
            <flux:input wire:model="nombre" label="Nombre" required />
            <flux:input wire:model="telefono" label="Teléfono" />
            @error('comprador.ci')
                <div class="sm:col-span-3"><flux:callout variant="danger" icon="x-circle" :heading="$message" /></div>
            @enderror
        </flux:card>

        <flux:card class="grid gap-4 sm:grid-cols-3">
            <flux:heading size="lg" class="sm:col-span-3">Condiciones</flux:heading>
            <flux:radio.group wire:model.live="tipo" label="Tipo de venta" variant="segmented">
                <flux:radio value="credito" label="A crédito" />
                <flux:radio value="contado" label="Al contado" />
            </flux:radio.group>
            <flux:select wire:model.live="moneda" label="Moneda de referencia">
                @foreach (\App\Models\Venta::MONEDAS as $valor => $texto)
                    <flux:select.option value="{{ $valor }}">{{ $texto }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="fecha_venta" label="Fecha de venta" type="date" required />

            <flux:select wire:model.live="regla_tc" label="Tipo de cambio para cobrar">
                @foreach (\App\Models\Venta::REGLAS_TC as $valor => $texto)
                    <flux:select.option value="{{ $valor }}">{{ $texto }}</flux:select.option>
                @endforeach
            </flux:select>
            @if ($moneda === 'BOB' || $regla_tc === 'pactado')
                <flux:input wire:model="tc_pactado" label="Tipo de cambio pactado" inputmode="decimal" placeholder="12.26" required />
            @endif
            <flux:input wire:model.live.debounce.500ms="precio" label="Precio de venta ({{ $moneda === 'BOB' ? 'Bs' : 'USD' }})" inputmode="decimal" required />
        </flux:card>

        @if ($tipo === 'credito')
            <flux:card class="grid gap-4 sm:grid-cols-4">
                <flux:heading size="lg" class="sm:col-span-4">Plan de cuotas</flux:heading>
                <flux:input wire:model.live.debounce.500ms="inicial" label="Cuota inicial" inputmode="decimal" required />
                <flux:input wire:model.live.debounce.500ms="numero_cuotas" label="Número de cuotas" type="number" min="1" max="120" required />
                <flux:input wire:model.live.debounce.500ms="primer_vencimiento" label="Primer vencimiento" type="date" required />
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <flux:input wire:model.live.debounce.500ms="monto_cuota" label="Cuota" inputmode="decimal" required />
                    </div>
                    <flux:button type="button" wire:click="calcularCuota" icon="calculator" class="mb-0.5">Calcular</flux:button>
                </div>
                <div class="sm:col-span-4">
                    <flux:error name="monto_cuota" />
                    <flux:error name="inicial" />
                </div>

                @if ($this->plan)
                    <div class="sm:col-span-4 overflow-x-auto">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Cuota</flux:table.column>
                                <flux:table.column>Vence</flux:table.column>
                                <flux:table.column align="end">Monto</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($this->plan as $cuota)
                                    <flux:table.row>
                                        <flux:table.cell>{{ $cuota['numero'] }}</flux:table.cell>
                                        <flux:table.cell>{{ $cuota['vencimiento']->format('d/m/Y') }}</flux:table.cell>
                                        <flux:table.cell align="end">{{ Formato::monto($cuota['monto']) }}</flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @endif
            </flux:card>
        @endif

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" icon="check">Registrar venta</flux:button>
            <flux:button :href="route('vehiculos.ver', $vehiculo)" wire:navigate variant="ghost">Cancelar</flux:button>
        </div>
    </form>
</div>
