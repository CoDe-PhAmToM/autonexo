<?php

use App\Models\Negocio;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * PBI-02: el registro da de alta un negocio nuevo y deja a quien se registra como su dueño.
 * Los demás usuarios (cajero, vendedor, almacenero) los crea el dueño desde "Usuarios".
 */
new #[Layout('components.layouts.auth')] class extends Component {
    public string $negocio = '';
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register(): void
    {
        $validated = $this->validate([
            'negocio' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ], [], ['negocio' => 'nombre del negocio', 'name' => 'nombre']);

        $user = DB::transaction(function () use ($validated) {
            $negocio = Negocio::create(['nombre' => $validated['negocio']]);

            $user = User::create([
                'negocio_id' => $negocio->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $user->assignRole(Roles::DUENO);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Registra tu negocio" description="Crea la cuenta del dueño; después agregas a tu equipo" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="register" class="flex flex-col gap-6">
        <flux:input wire:model="negocio" label="Nombre del negocio" type="text" required autofocus placeholder="Automotores El Alto" />

        <flux:input wire:model="name" label="Tu nombre" type="text" required autocomplete="name" placeholder="Nombre y apellido" />

        <flux:input wire:model="email" label="Correo electrónico" type="email" required autocomplete="email" placeholder="correo@ejemplo.com" />

        <flux:input wire:model="password" label="Contraseña" type="password" required autocomplete="new-password" placeholder="Mínimo 8 caracteres" />

        <flux:input wire:model="password_confirmation" label="Repite la contraseña" type="password" required autocomplete="new-password" />

        <flux:button type="submit" variant="primary" class="w-full">Crear cuenta</flux:button>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
        ¿Ya tienes cuenta?
        <x-text-link href="{{ route('login') }}">Inicia sesión</x-text-link>
    </div>
</div>
