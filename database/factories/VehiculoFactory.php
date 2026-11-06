<?php

namespace Database\Factories;

use App\Models\Negocio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehiculo>
 */
class VehiculoFactory extends Factory
{
    /** Modelos comunes en el mercado de El Alto (dato del caso). */
    private const MODELOS = [
        ['Toyota', 'Corolla'], ['Toyota', 'Hilux'], ['Toyota', 'RAV4'], ['Toyota', 'Rush'],
        ['Suzuki', 'Swift'], ['Suzuki', 'Vitara'], ['Nissan', 'Sentra'], ['Nissan', 'Frontier'],
        ['Hyundai', 'Accent'], ['Hyundai', 'Tucson'], ['Kia', 'Rio'], ['Kia', 'Sportage'],
        ['Chevrolet', 'Onix'], ['Mitsubishi', 'L200'], ['Volkswagen', 'Amarok'],
    ];

    public function definition(): array
    {
        [$marca, $modelo] = fake()->randomElement(self::MODELOS);
        $costo = fake()->numberBetween(60, 380) * 50;              // USD 3.000 a 19.000

        return [
            'negocio_id' => Negocio::factory(),
            'chasis' => strtoupper(fake()->bothify('?##??#####?######')),
            'placa' => fake()->numerify('####').strtoupper(fake()->lexify('???')),
            'marca' => $marca,
            'modelo' => $modelo,
            'anio' => fake()->numberBetween(2008, 2024),
            'color' => fake()->randomElement(['Blanco', 'Plata', 'Negro', 'Rojo', 'Gris', 'Azul']),
            'costo_usd' => $costo.'.00',
            'precio_usd' => ($costo + fake()->numberBetween(8, 40) * 50).'.00',
            'estado' => 'en_exhibicion',
        ];
    }
}
