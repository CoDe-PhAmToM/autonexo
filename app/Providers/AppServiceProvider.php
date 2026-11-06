<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
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
        ]);
    }
}
