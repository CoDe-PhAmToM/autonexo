<?php

namespace Database\Factories;

use App\Models\Negocio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comprador>
 */
class CompradorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'negocio_id' => Negocio::factory(),
            'nombre' => fake()->name(),
            'ci' => (string) fake()->unique()->numberBetween(1000000, 14999999),
            'telefono' => '7'.fake()->numerify('#######'),
        ];
    }
}
