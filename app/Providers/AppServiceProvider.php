<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bajo `php artisan serve` (SAPI cli-server), Symfony Process arma el entorno de los
        // procesos hijos con getenv() ∩ $_SERVER, y ahí $_SERVER no trae las variables del
        // sistema. pg_dump arrancaba sin SystemRoot y en Windows no podía generar números
        // aleatorios (falla la autenticación SCRAM y la clave \restrict). Se copia el entorno.
        if (PHP_SAPI === 'cli-server') {
            foreach (getenv() as $nombre => $valor) {
                $_ENV[$nombre] ??= $valor;
            }
        }
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

        // PBI-21: cada respaldo deja su resultado; los fallidos quedan visibles.
        Event::listen(\Spatie\Backup\Events\BackupWasSuccessful::class, function ($evento) {
            $disco = \Illuminate\Support\Facades\Storage::disk($evento->diskName);
            $ultimo = collect($disco->allFiles($evento->backupName))
                ->filter(fn ($f) => str_ends_with($f, '.zip'))
                ->sortByDesc(fn ($f) => $disco->lastModified($f))
                ->first();

            \App\Models\Respaldo::create([
                'user_id' => auth()->id(),
                'estado' => 'exitoso',
                'archivo' => $ultimo ? basename($ultimo) : null,
                'bytes' => $ultimo ? $disco->size($ultimo) : null,
            ]);
        });
        Event::listen(\Spatie\Backup\Events\BackupHasFailed::class, fn ($evento) => \App\Models\Respaldo::create([
            'user_id' => auth()->id(),
            'estado' => 'fallido',
            'mensaje' => mb_substr($evento->exception->getMessage(), 0, 2000),
        ]));

        // Ver ventas: quien vende, cobra o revisa la cartera.
        Gate::define('ver-ventas', fn ($user) => $user->canAny(['ventas.registrar', 'cobros.registrar', 'cartera.ver']));
    }
}
