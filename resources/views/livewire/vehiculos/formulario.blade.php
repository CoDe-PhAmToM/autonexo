<?php

use App\Models\Vehiculo;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * PBI-03 (CA-03): registrar o corregir un vehículo.
 * - El chasis duplicado dentro del negocio se detecta.
 * - Corregir la placa actualiza el mismo vehículo y deja el cambio en su historial.
 */
new #[Title('Vehículo')] class extends Component {
    use WithFileUploads;

    public ?Vehiculo $vehiculo = null;

    public string $chasis = '';
    public string $placa = '';
    public string $marca = '';
    public string $modelo = '';
    public string $anio = '';
    public string $color = '';
    public string $costo_usd = '';
    public string $precio_usd = '';
    public string $observaciones = '';
    public $foto = null;

    public function mount(?Vehiculo $vehiculo = null): void
    {
        abort_unless(auth()->user()->can('vehiculos.gestionar'), 403);

        if ($vehiculo?->exists) {
            $this->vehiculo = $vehiculo;
            $this->fill($vehiculo->only('chasis', 'marca', 'modelo', 'costo_usd', 'precio_usd'));
            $this->placa = (string) $vehiculo->placa;
            $this->anio = (string) $vehiculo->anio;
            $this->color = (string) $vehiculo->color;
            $this->observaciones = (string) $vehiculo->observaciones;
        }
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()->can('vehiculos.gestionar'), 403);

        $this->chasis = Vehiculo::normalizarChasis($this->chasis);
        $monto = ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];

        $datos = $this->validate([
            'chasis' => [
                'required', 'string', 'min:5', 'max:30',
                Rule::unique('vehiculos', 'chasis')
                    ->where('negocio_id', auth()->user()->negocio_id)
                    ->ignore($this->vehiculo?->id),
            ],
            'placa' => ['nullable', 'string', 'max:15'],
            'marca' => ['required', 'string', 'max:60'],
            'modelo' => ['required', 'string', 'max:60'],
            'anio' => ['required', 'integer', 'between:1950,'.(now()->year + 1)],
            'color' => ['nullable', 'string', 'max:30'],
            'costo_usd' => $monto,
            'precio_usd' => $monto,
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'foto' => ['nullable', 'image', 'max:2048'],
        ], [
            'chasis.unique' => 'Ya existe un vehículo con este chasis en tu negocio.',
            'costo_usd.regex' => 'Escribe el costo con punto y hasta 2 decimales (ej. 8500.00).',
            'precio_usd.regex' => 'Escribe el precio con punto y hasta 2 decimales (ej. 9500.00).',
        ], [
            'anio' => 'año', 'costo_usd' => 'costo', 'precio_usd' => 'precio',
        ]);

        unset($datos['foto']);
        $datos = array_map(fn ($v) => $v === '' ? null : $v, $datos);

        if ($this->foto) {
            $datos['foto_path'] = $this->foto->store('vehiculos/'.auth()->user()->negocio_id, 'local');
        }

        if ($this->vehiculo) {
            $this->vehiculo->update($datos);
            $vehiculo = $this->vehiculo;
        } else {
            $vehiculo = Vehiculo::create($datos);
        }

        session()->flash('estado', 'Vehículo guardado.');
        $this->redirectRoute('vehiculos.ver', $vehiculo, navigate: true);
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ $vehiculo ? 'Corregir vehículo' : 'Nuevo vehículo' }}</flux:heading>
        <flux:subheading>
            {{ $vehiculo ? 'Los cambios quedan en el historial del vehículo.' : 'El chasis identifica al vehículo y no puede repetirse.' }}
        </flux:subheading>
    </div>

    <flux:card>
        <form wire:submit="guardar" class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <flux:input wire:model="chasis" label="Chasis (VIN)" required placeholder="JTDBR32E720123456" />
            </div>
            <flux:input wire:model="placa" label="Placa" placeholder="2345ABC" />
            <flux:input wire:model="color" label="Color" />
            <flux:input wire:model="marca" label="Marca" required placeholder="Toyota" />
            <flux:input wire:model="modelo" label="Modelo" required placeholder="Corolla" />
            <flux:input wire:model="anio" label="Año" type="number" required />
            <div></div>
            <flux:input wire:model="costo_usd" label="Costo (USD)" inputmode="decimal" required placeholder="8500.00" />
            <flux:input wire:model="precio_usd" label="Precio de venta (USD)" inputmode="decimal" required placeholder="9500.00" />
            <div class="sm:col-span-2">
                <flux:textarea wire:model="observaciones" label="Observaciones" rows="2" />
            </div>
            <div class="sm:col-span-2">
                <flux:input wire:model="foto" type="file" accept="image/*" label="Foto (opcional, hasta 2 MB)" />
            </div>

            <div class="flex gap-3 sm:col-span-2">
                <flux:button type="submit" variant="primary">Guardar</flux:button>
                <flux:button :href="$vehiculo ? route('vehiculos.ver', $vehiculo) : route('vehiculos.index')" wire:navigate variant="ghost">Cancelar</flux:button>
            </div>
        </form>
    </flux:card>
</div>
