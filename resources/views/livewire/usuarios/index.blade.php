<?php

use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/**
 * PBI-02 (CA-02): el dueño crea usuarios de su negocio con un rol.
 * Se impiden los correos duplicados y las altas de quien no tiene permiso.
 */
new #[Title('Usuarios')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $rol = Roles::CAJERO;
    public string $password = '';

    public function mount(): void
    {
        $this->autorizar();
    }

    #[Computed]
    public function usuarios()
    {
        return User::where('negocio_id', auth()->user()->negocio_id)
            ->with('roles')
            ->orderBy('name')
            ->get();
    }

    public function crear(): void
    {
        $this->autorizar();

        $datos = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'rol' => ['required', Rule::in(array_keys(Roles::NOMBRES))],
            'password' => ['required', 'string', Rules\Password::defaults()],
        ], [], ['name' => 'nombre', 'email' => 'correo', 'password' => 'contraseña']);

        DB::transaction(function () use ($datos) {
            $user = User::create([
                'negocio_id' => auth()->user()->negocio_id,
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => $datos['password'],
                'email_verified_at' => now(),
            ]);
            $user->assignRole($datos['rol']);
        });

        $this->reset('name', 'email', 'password');
        $this->rol = Roles::CAJERO;
        unset($this->usuarios);

        $this->dispatch('usuario-creado');
    }

    private function autorizar(): void
    {
        abort_unless(auth()->user()->can('usuarios.gestionar'), 403);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">Usuarios</flux:heading>
        <flux:subheading>Tu equipo en {{ auth()->user()->negocio->nombre }}. Cada registro queda a nombre de quien lo hizo.</flux:subheading>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="lg:col-span-1">
            <flux:heading size="lg" class="mb-4">Nuevo usuario</flux:heading>

            <form wire:submit="crear" class="flex flex-col gap-4">
                <flux:input wire:model="name" label="Nombre" required />
                <flux:input wire:model="email" label="Correo" type="email" required />
                <flux:select wire:model="rol" label="Rol">
                    @foreach (\App\Support\Roles::NOMBRES as $valor => $nombre)
                        <flux:select.option value="{{ $valor }}">{{ $nombre }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="password" label="Contraseña inicial" type="password" required />

                <div class="flex items-center gap-3">
                    <flux:button type="submit" variant="primary">Crear usuario</flux:button>
                    <x-action-message on="usuario-creado">Usuario creado.</x-action-message>
                </div>
            </form>
        </flux:card>

        <flux:card class="lg:col-span-2 overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Nombre</flux:table.column>
                    <flux:table.column>Correo</flux:table.column>
                    <flux:table.column>Rol</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->usuarios as $usuario)
                        <flux:table.row :key="$usuario->id">
                            <flux:table.cell variant="strong">{{ $usuario->name }}</flux:table.cell>
                            <flux:table.cell>{{ $usuario->email }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$usuario->hasRole('dueño') ? 'indigo' : 'zinc'">{{ $usuario->nombreRol() }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
</div>
