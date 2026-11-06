<?php

namespace Database\Seeders;

use App\Models\Negocio;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración: un negocio con un usuario por rol.
 * Contraseña de prueba de todos: "password" (solo para la demo local).
 *
 *   php artisan db:seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $negocio = Negocio::firstOrCreate(
            ['nombre' => 'Automotores Illimani (demo)'],
            ['nit' => '4589217', 'ciudad' => 'El Alto', 'telefono' => '71234567'],
        );

        $usuarios = [
            Roles::DUENO => ['Fernando Quispe', 'dueno@autonexo.test'],
            Roles::CAJERO => ['Rosa Mamani', 'cajero@autonexo.test'],
            Roles::VENDEDOR => ['Luis Condori', 'vendedor@autonexo.test'],
            Roles::ALMACENERO => ['Marco Choque', 'almacen@autonexo.test'],
        ];

        foreach ($usuarios as $rol => [$nombre, $correo]) {
            $user = User::firstOrCreate(
                ['email' => $correo],
                ['negocio_id' => $negocio->id, 'name' => $nombre, 'password' => 'password', 'email_verified_at' => now()],
            );
            $user->syncRoles([$rol]);
        }

        $this->call(VehiculosDemoSeeder::class);
    }
}
