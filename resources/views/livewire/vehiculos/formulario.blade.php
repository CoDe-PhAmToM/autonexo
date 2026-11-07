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
    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $fotos = [];

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
            'fotos' => ['array', 'max:'.(Vehiculo::MAX_FOTOS - ($this->vehiculo?->fotos()->count() ?? 0))],
            'fotos.*' => ['image', 'max:4096'],
        ], [
            'chasis.unique' => 'Ya existe un vehículo con este chasis en tu negocio.',
            'costo_usd.regex' => 'Escribe el costo con punto y hasta 2 decimales (ej. 8500.00).',
            'fotos.max' => 'Cada vehículo puede tener hasta '.Vehiculo::MAX_FOTOS.' fotos.',
            'fotos.*.image' => 'Cada archivo debe ser una imagen.',
            'fotos.*.max' => 'Cada foto puede pesar hasta 4 MB.',
            'precio_usd.regex' => 'Escribe el precio con punto y hasta 2 decimales (ej. 9500.00).',
        ], [
            'anio' => 'año', 'costo_usd' => 'costo', 'precio_usd' => 'precio',
        ]);

        unset($datos['fotos'], $datos['fotos.*']);
        $datos = array_map(fn ($v) => $v === '' ? null : $v, $datos);

        if ($this->vehiculo) {
            $this->vehiculo->update($datos);
            $vehiculo = $this->vehiculo;
        } else {
            $vehiculo = Vehiculo::create($datos);
        }

        $vehiculo->agregarFotos($this->fotos);

        session()->flash('estado', 'Vehículo guardado.');
        $this->redirectRoute('vehiculos.ver', $vehiculo, navigate: true);
    }

    public function quitarNueva(int $indice): void
    {
        unset($this->fotos[$indice]);
        $this->fotos = array_values($this->fotos);
    }

    public function quitarFoto(int $fotoId): void
    {
        abort_unless(auth()->user()->can('vehiculos.gestionar') && $this->vehiculo, 403);
        $this->vehiculo->quitarFoto($this->vehiculo->fotos()->findOrFail($fotoId));
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
            <div class="flex flex-col gap-3 sm:col-span-2">
                <flux:input wire:model="fotos" type="file" accept="image/*" multiple
                    label="Fotos (hasta {{ Vehiculo::MAX_FOTOS }}, 4 MB cada una)"
                    description="Frente, costado, interior… La primera es la portada en la vitrina." />
                <flux:error name="fotos" />
                <flux:error name="fotos.*" />

                @php $actuales = $vehiculo?->fotos ?? collect(); @endphp
                @if ($actuales->isNotEmpty() || $fotos)
                    <div class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                        @foreach ($actuales as $f)
                            <div class="group relative aspect-[4/3] overflow-hidden rounded-xl border border-white/10" wire:key="actual-{{ $f->id }}">
                                <img src="{{ route('vehiculos.fotos.ver', [$vehiculo, $f]) }}" alt="Foto {{ $loop->iteration }}" class="size-full object-cover" loading="lazy">
                                @if ($loop->first)
                                    <span class="absolute left-1.5 top-1.5 rounded-full bg-noche/70 px-2 py-0.5 font-mono text-[10px] uppercase tracking-wider text-faro">Portada</span>
                                @endif
                                <button type="button" wire:click="quitarFoto({{ $f->id }})" wire:confirm="¿Quitar esta foto?"
                                    class="absolute right-1.5 top-1.5 rounded-full bg-noche/80 p-1 text-cromo opacity-80 transition hover:bg-mora hover:opacity-100" aria-label="Quitar foto">
                                    <flux:icon name="x-mark" class="size-4" />
                                </button>
                            </div>
                        @endforeach
                        @foreach ($fotos as $i => $nueva)
                            <div class="relative aspect-[4/3] overflow-hidden rounded-xl border border-faro/40" wire:key="nueva-{{ $i }}">
                                @if (method_exists($nueva, 'isPreviewable') && $nueva->isPreviewable())
                                    <img src="{{ $nueva->temporaryUrl() }}" alt="Foto nueva" class="size-full object-cover">
                                @endif
                                <span class="absolute left-1.5 top-1.5 rounded-full bg-noche/70 px-2 py-0.5 font-mono text-[10px] uppercase tracking-wider text-pago">Nueva</span>
                                <button type="button" wire:click="quitarNueva({{ $i }})"
                                    class="absolute right-1.5 top-1.5 rounded-full bg-noche/80 p-1 text-cromo hover:bg-mora" aria-label="Quitar foto nueva">
                                    <flux:icon name="x-mark" class="size-4" />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex gap-3 sm:col-span-2">
                <flux:button type="submit" variant="primary">Guardar</flux:button>
                <flux:button :href="$vehiculo ? route('vehiculos.ver', $vehiculo) : route('vehiculos.index')" wire:navigate variant="ghost">Cancelar</flux:button>
            </div>
        </form>
    </flux:card>
</div>
