<?php

use App\Models\Vehiculo;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    // PBI-02: usuarios y roles
    Volt::route('usuarios', 'usuarios.index')->middleware('can:usuarios.gestionar')->name('usuarios.index');

    // PBI-03: vehículos
    Route::middleware('can:vehiculos.ver')->group(function () {
        Volt::route('vehiculos', 'vehiculos.index')->name('vehiculos.index');
        Volt::route('vehiculos/crear', 'vehiculos.formulario')->middleware('can:vehiculos.gestionar')->name('vehiculos.crear');
        Volt::route('vehiculos/{vehiculo}', 'vehiculos.ver')->name('vehiculos.ver');
        Volt::route('vehiculos/{vehiculo}/editar', 'vehiculos.formulario')->middleware('can:vehiculos.gestionar')->name('vehiculos.editar');

        Route::get('vehiculos/{vehiculo}/foto', function (Vehiculo $vehiculo) {
            abort_unless($vehiculo->foto_path && Storage::disk('local')->exists($vehiculo->foto_path), 404);

            return Storage::disk('local')->response($vehiculo->foto_path);
        })->name('vehiculos.foto');
    });
});

require __DIR__.'/auth.php';
