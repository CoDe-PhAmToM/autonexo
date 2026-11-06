<?php

use App\Importacion\PlantillaCarga;
use App\Models\Cobro;
use App\Models\Vehiculo;
use Maatwebsite\Excel\Facades\Excel;
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

        // PBI-05 y PBI-06: reservar y vender
        Route::middleware('can:ventas.registrar')->group(function () {
            Volt::route('vehiculos/{vehiculo}/reservar', 'vehiculos.reservar')->name('vehiculos.reservar');
            Volt::route('vehiculos/{vehiculo}/vender', 'ventas.crear')->name('ventas.crear');
        });
    });

    // PBI-06: ventas (las ven quienes venden, cobran o revisan la cartera)
    Route::middleware('can:ver-ventas')->group(function () {
        Volt::route('ventas', 'ventas.index')->name('ventas.index');
        Volt::route('ventas/{venta}', 'ventas.ver')->name('ventas.ver');
    });

    // PBI-07: cobros
    Route::middleware('can:cobros.registrar')->group(function () {
        Volt::route('cobros', 'cobros.index')->name('cobros.index');
        Volt::route('ventas/{venta}/cobrar', 'ventas.cobrar')->name('ventas.cobrar');
    });
    Route::get('cobros/{cobro}/comprobante', function (Cobro $cobro) {
        abort_unless($cobro->comprobante_path && Storage::disk('local')->exists($cobro->comprobante_path), 404);

        return Storage::disk('local')->response($cobro->comprobante_path);
    })->middleware('can:ver-ventas')->name('cobros.comprobante');

    // PBI-10: transferencias
    Route::middleware('can:transferencias.gestionar')->group(function () {
        Volt::route('transferencias', 'transferencias.index')->name('transferencias.index');
        Volt::route('transferencias/{transferencia}', 'transferencias.ver')->name('transferencias.ver');
    });

    // PBI-13: carga inicial desde Excel
    Route::middleware('can:datos.importar')->group(function () {
        Volt::route('carga-inicial', 'carga.index')->name('carga.index');
        Route::get('carga-inicial/plantilla', fn () => Excel::download(new PlantillaCarga, 'plantilla_autonexo.xlsx'))->name('carga.plantilla');
    });

    // PBI-11: kárdex de repuestos
    Route::middleware('can:repuestos.gestionar')->group(function () {
        Volt::route('repuestos', 'repuestos.index')->name('repuestos.index');
        Volt::route('repuestos/{repuesto}', 'repuestos.ver')->name('repuestos.ver');
    });
});

require __DIR__.'/auth.php';
