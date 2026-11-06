<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Nombres cortos en la bitácora (auditable_type = "vehiculo" en lugar de la clase completa).
        Relation::morphMap([
            'vehiculo' => \App\Models\Vehiculo::class,
            'reserva' => \App\Models\Reserva::class,
            'venta' => \App\Models\Venta::class,
            'cobro' => \App\Models\Cobro::class,
            'transferencia' => \App\Models\Transferencia::class,
        ]);

        // Ver ventas: quien vende, cobra o revisa la cartera.
        Gate::define('ver-ventas', fn ($user) => $user->canAny(['ventas.registrar', 'cobros.registrar', 'cartera.ver']));
    }
}
