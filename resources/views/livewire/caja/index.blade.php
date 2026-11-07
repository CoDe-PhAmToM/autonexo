<?php

use App\Models\CierreCaja;
use App\Servicios\CierresDeCaja;
use App\Support\Formato;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/**
 * PBI-22 (CA-22): cierre de caja del día. El cajero declara lo que contó y el sistema
 * lo compara con lo registrado; las diferencias quedan guardadas.
 */
new #[Title('Cierre de caja')] class extends Component {
    /** @var array<string, string> */
    public array $declarado = ['efectivo_bob' => '', 'efectivo_usd' => '', 'qr_bob' => '', 'qr_usd' => ''];
    public string $observacion = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('caja.cerrar'), 403);
    }

    #[Computed]
    public function cierreDeHoy(): ?CierreCaja
    {
        return CierreCaja::where('user_id', auth()->id())->whereDate('fecha', today())->first();
    }

    #[Computed]
    public function registrado(): array
    {
        return app(CierresDeCaja::class)->registrado(auth()->user(), today());
    }

    #[Computed]
    public function historial()
    {
        $q = CierreCaja::with('user')->latest('fecha')->latest('id')->limit(30);

        // El dueño ve los cierres de todos; el cajero, solo los suyos.
        return auth()->user()->can('usuarios.gestionar') ? $q->get() : $q->where('user_id', auth()->id())->get();
    }

    public function cerrar(CierresDeCaja $cierres): void
    {
        abort_unless(auth()->user()->can('caja.cerrar'), 403);

        $reglas = [];
        foreach (array_keys(CierreCaja::COMBOS) as $combo) {
            $reglas["declarado.{$combo}"] = ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
        }
        $this->validate($reglas + ['observacion' => ['nullable', 'string', 'max:1000']], [
            'declarado.*.required' => 'Escribe lo que contaste (0 si no hubo).',
            'declarado.*.regex' => 'Usa punto y hasta 2 decimales.',
        ]);

        $cierres->cerrar(auth()->user(), $this->declarado, $this->observacion);
        unset($this->cierreDeHoy, $this->historial);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">Cierre de caja</flux:heading>
        <flux:subheading>{{ auth()->user()->name }} · {{ today()->translatedFormat('l j \d\e F') }}</flux:subheading>
    </div>

    @if ($cierre = $this->cierreDeHoy)
        <flux:callout :variant="$cierre->cuadra() ? 'success' : 'warning'" :icon="$cierre->cuadra() ? 'check-circle' : 'exclamation-triangle'"
            heading="{{ $cierre->cuadra() ? 'Caja cerrada: todo cuadra.' : 'Caja cerrada con diferencias registradas.' }}">
            @if ($cierre->observacion)
                <flux:callout.text>{{ $cierre->observacion }}</flux:callout.text>
            @endif
        </flux:callout>
    @else
        <flux:card>
            <form wire:submit="cerrar" class="flex flex-col gap-4">
                <flux:text>Cuenta el efectivo y revisa los QR recibidos hoy. Escribe lo que tienes; el sistema lo compara con tus {{ $this->registrado['cobros'] }} cobro(s) registrados.</flux:text>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-zinc-500">
                                <th class="py-2 font-medium">Medio</th>
                                <th class="py-2 text-right font-medium">Registrado</th>
                                <th class="py-2 pl-4 font-medium">Contado</th>
                                <th class="py-2 text-right font-medium">Diferencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (CierreCaja::COMBOS as $combo => [$etiqueta, $sim])
                                @php
                                    $reg = $this->registrado['montos'][$combo];
                                    $dec = $declarado[$combo];
                                    $dif = preg_match('/^\d{1,12}(\.\d{1,2})?$/', $dec)
                                        ? \App\Servicios\CalculadoraDePlan::aCentavos($dec) - \App\Servicios\CalculadoraDePlan::aCentavos($reg)
                                        : null;
                                @endphp
                                <tr class="border-t border-zinc-200 dark:border-zinc-700" wire:key="combo-{{ $combo }}">
                                    <td class="py-2">{{ $etiqueta }}</td>
                                    <td class="py-2 text-right">{{ $sim }} {{ Formato::monto($reg) }}</td>
                                    <td class="py-2 pl-4"><flux:input wire:model.live.debounce.400ms="declarado.{{ $combo }}" inputmode="decimal" size="sm" placeholder="0.00" /></td>
                                    <td class="py-2 text-right font-medium {{ $dif === null || $dif === 0 ? '' : ($dif > 0 ? 'text-blue-500' : 'text-red-500') }}">
                                        @if ($dif !== null)
                                            {{ $dif === 0 ? 'Cuadra' : ($dif > 0 ? 'Sobra ' : 'Falta ').$sim.' '.Formato::monto(\App\Servicios\CalculadoraDePlan::aTexto(abs($dif))) }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <flux:error name="declarado.*" />
                <flux:textarea wire:model="observacion" label="Observación (obligatoria si hay diferencias)" rows="2" />
                <flux:error name="cierre" />
                <div>
                    <flux:button type="submit" variant="primary" icon="lock-closed">Cerrar caja</flux:button>
                </div>
            </form>
        </flux:card>
    @endif

    <flux:card class="overflow-x-auto">
        <flux:heading size="lg" class="mb-3">Cierres anteriores</flux:heading>
        @if ($this->historial->isEmpty())
            <flux:text>Todavía no hay cierres.</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Fecha</flux:table.column>
                    <flux:table.column>Cajero</flux:table.column>
                    <flux:table.column align="end">Cobros</flux:table.column>
                    <flux:table.column>Resultado</flux:table.column>
                    <flux:table.column>Diferencias</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->historial as $c)
                        <flux:table.row :key="'cierre-'.$c->id">
                            <flux:table.cell>{{ $c->fecha->format('d/m/Y') }}</flux:table.cell>
                            <flux:table.cell>{{ $c->user->name }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $c->cobros }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$c->cuadra() ? 'green' : 'red'">{{ $c->cuadra() ? 'Cuadra' : 'Con diferencias' }}</flux:badge></flux:table.cell>
                            <flux:table.cell class="whitespace-normal text-xs">
                                @foreach (CierreCaja::COMBOS as $combo => [$etiqueta, $sim])
                                    @if ((float) $c->{$combo.'_diferencia'} != 0)
                                        <div>{{ $etiqueta }}: {{ $sim }} {{ Formato::monto($c->{$combo.'_diferencia'}) }}</div>
                                    @endif
                                @endforeach
                                @if ($c->observacion)<div class="text-zinc-500">{{ $c->observacion }}</div>@endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
