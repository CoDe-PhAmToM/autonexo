<?php

namespace Database\Seeders;

use App\Models\Negocio;
use App\Models\Vehiculo;
use Illuminate\Database\Seeder;

/** Doce vehículos de demostración para el negocio demo. */
class VehiculosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $negocio = Negocio::where('nombre', 'Automotores Illimani (demo)')->firstOrFail();

        if (Vehiculo::withoutGlobalScopes()->where('negocio_id', $negocio->id)->exists()) {
            return;
        }

        Vehiculo::factory()->count(12)->create(['negocio_id' => $negocio->id]);
    }
}
