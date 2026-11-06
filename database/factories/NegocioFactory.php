<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Negocio>
 */
class NegocioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'Automotores '.fake()->lastName(),
            'nit' => (string) fake()->numberBetween(1000000, 9999999),
            'ciudad' => 'El Alto',
            'telefono' => '7'.fake()->numerify('#######'),
        ];
    }
}
