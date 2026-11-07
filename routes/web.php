<?php

use App\Importacion\PlantillaCarga;
use App\Models\Cobro;
use App\Models\Vehiculo;
use App\Models\VehiculoFoto;
use App\Support\Imagenes;
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

    // PBI-02: usuarios y roles
    Volt::route('usuarios', 'usuarios.index')->middleware('can:usuarios.gestionar')->name('usuarios.index');

    // PBI-03: vehículos
    Route::middleware('can:vehiculos.ver')->group(function () {
        Volt::route('vehiculos', 'vehiculos.index')->name('vehiculos.index');
        Volt::route('vehiculos/crear', 'vehiculos.formulario')->middleware('can:vehiculos.gestionar')->name('vehiculos.crear');
        Volt::route('vehiculos/{vehiculo}', 'vehiculos.ver')->name('vehiculos.ver');
        Volt::route('vehiculos/{vehiculo}/editar', 'vehiculos.formulario')->middleware('can:vehiculos.gestionar')->name('vehiculos.editar');

        // Fotos privadas: solo las ve el negocio dueño del vehículo.
        Route::get('vehiculos/{vehiculo}/foto', fn (Vehiculo $vehiculo) => Imagenes::responder($vehiculo->portada()?->path))
            ->name('vehiculos.foto');
        Route::get('vehiculos/{vehiculo}/fotos/{foto}', fn (Vehiculo $vehiculo, VehiculoFoto $foto) => Imagenes::responder($foto->path))
            ->scopeBindings()
            ->name('vehiculos.fotos.ver');

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

    // PBI-12: comprobante y estado de cuenta (requieren el consentimiento del comprador)
    Route::middleware('can:ver-ventas')->group(function () {
        Route::get('cobros/{cobro}/pdf', fn (Cobro $cobro) => app(\App\Servicios\Comprobantes::class)->pdfDelCobro($cobro))->name('cobros.pdf');
        Route::get('ventas/{venta}/estado-de-cuenta', fn (\App\Models\Venta $venta) => app(\App\Servicios\Comprobantes::class)->pdfEstadoDeCuenta($venta))->name('ventas.estado-cuenta');
    });

    // PBI-23: medición de uso
    Volt::route('medicion', 'metricas.index')->middleware('can:metricas.ver')->name('metricas.index');

    // PBI-08: cartera y mora
    Volt::route('cartera', 'cartera.index')->middleware('can:cartera.ver')->name('cartera.index');

    // PBI-22: cierre de caja
    Volt::route('caja', 'caja.index')->middleware('can:caja.cerrar')->name('caja.index');

    // PBI-21: respaldos y exportación (solo el dueño)
    Route::middleware('can:respaldos.gestionar')->group(function () {
        Volt::route('respaldos', 'respaldos.index')->name('respaldos.index');
        Route::get('respaldos/exportar', fn () => Excel::download(
            new \App\Importacion\RegistrosDelNegocio,
            'autonexo_registros_'.now()->format('Y-m-d').'.xlsx',
        ))->name('respaldos.exportar');
    });

    // PBI-11: kárdex de repuestos
    Route::middleware('can:repuestos.gestionar')->group(function () {
        Volt::route('repuestos', 'repuestos.index')->name('repuestos.index');
        Volt::route('repuestos/{repuesto}', 'repuestos.ver')->name('repuestos.ver');
        Route::get('repuestos/{repuesto}/foto', fn (\App\Models\Repuesto $repuesto) => Imagenes::responder($repuesto->foto_path))
            ->name('repuestos.foto');
    });
});

// PBI-12: el comprador descarga su comprobante con el enlace firmado del mensaje de WhatsApp
// (sin iniciar sesión; vence en 7 días y no se puede alterar).
Route::get('comprobante/{cobro}', function (int $cobro) {
    $cobro = Cobro::withoutGlobalScopes()->findOrFail($cobro);

    return app(\App\Servicios\Comprobantes::class)->pdfDelCobro($cobro);
})->middleware(['signed', 'throttle:30,1'])->name('comprobantes.publico');

require __DIR__.'/auth.php';
