<?php

use App\Models\Comprador;
use App\Models\Vehiculo;
use App\Servicios\Ventas;
use App\Support\Formato;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/**
 * PBI-05 (CA-05): reserva con anticipo y fecha límite.
 */
new #[Title('Reservar')] class extends Component {
    public Vehiculo $vehiculo;

    public string $ci = '';
    public string $nombre = '';
    public string $telefono = '';
    public string $anticipo = '';
    public string $moneda = 'USD';
    public string $fecha_limite = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('ventas.registrar'), 403);
        $this->fecha_limite = today()->addDays(7)->toDateString();
    }

    /** Si el CI ya existe en el negocio, completa el nombre y el teléfono. */
    public function updatedCi(): void
    {
        $comprador = Comprador::where('ci', Ventas::normalizarCi($this->ci))->first();
        if ($comprador) {
            $this->nombre = $comprador->nombre;
            $this->telefono = (string) $comprador->telefono;
        }
    }

    public function reservar(Ventas $ventas): void
    {
        abort_unless(auth()->user()->can('ventas.registrar'), 403);

        $this->validate([
            'ci' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'anticipo' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'moneda' => ['required', Rule::in(['USD', 'BOB'])],
            'fecha_limite' => ['required', 'date', 'after_or_equal:today'],
        ], [
            'anticipo.regex' => 'Escribe el anticipo con punto y hasta 2 decimales (ej. 500.00).',
            'fecha_limite.after_or_equal' => 'La fecha límite no puede estar en el pasado.',
        ], ['ci' => 'CI', 'fecha_limite' => 'fecha límite']);

        $ventas->reservar(
            $this->vehiculo,
            ['ci' => $this->ci, 'nombre' => $this->nombre, 'telefono' => $this->telefono ?: null],
            $this->anticipo,
            $this->moneda,
            $this->fecha_limite,
        );

        session()->flash('estado', 'Vehículo reservado.');
        $this->redirectRoute('vehiculos.ver', $this->vehiculo, navigate: true);
    }
}; ?>

<div class="flex max-w-2xl flex-col gap-6">
    <div>
        <flux:heading size="xl">Reservar {{ $vehiculo->descripcion() }}</flux:heading>
        <flux:subheading>Precio {{ Formato::usd($vehiculo->precio_usd) }} · chasis {{ $vehiculo->chasis }}</flux:subheading>
    </div>

    @error('vehiculo')
        <flux:callout variant="danger" icon="x-circle" :heading="$message" />
    @enderror

    <flux:card>
        <form wire:submit="reservar" class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model.blur="ci" label="CI del comprador" required />
            <flux:input wire:model="telefono" label="Teléfono" />
            <div class="sm:col-span-2">
                <flux:input wire:model="nombre" label="Nombre del comprador" required />
            </div>
            <flux:input wire:model="anticipo" label="Anticipo" inputmode="decimal" required placeholder="500.00" />
            <flux:select wire:model="moneda" label="Moneda del anticipo">
                <flux:select.option value="USD">Dólares (USD)</flux:select.option>
                <flux:select.option value="BOB">Bolivianos (Bs)</flux:select.option>
            </flux:select>
            <flux:input wire:model="fecha_limite" label="Fecha límite" type="date" required />

            <div class="flex gap-3 sm:col-span-2">
                <flux:button type="submit" variant="primary">Reservar</flux:button>
                <flux:button :href="route('vehiculos.ver', $vehiculo)" wire:navigate variant="ghost">Cancelar</flux:button>
            </div>
        </form>
    </flux:card>
</div>
