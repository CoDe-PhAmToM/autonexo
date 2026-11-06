<?php

namespace Database\Factories;

use App\Models\Negocio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Repuesto>
 */
class RepuestoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'negocio_id' => Negocio::factory(),
            'codigo' => strtoupper(fake()->unique()->bothify('REP-###??')),
            'nombre' => fake()->randomElement(['Filtro de aceite', 'Pastillas de freno', 'Bujía', 'Filtro de aire', 'Amortiguador', 'Batería 12V']),
            'unidad' => 'pieza',
            'stock_minimo' => 2,
            'precio_bs' => fake()->numberBetween(30, 900).'.00',
        ];
    }
}
