<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Venta a crédito del Entregable: USD 9.500, inicial 2.500 y 14 cuotas de 500.
 * Requiere un usuario autenticado (la venta queda en su negocio).
 */
function ventaDelEntregable(array $cambios = []): \App\Models\Venta
{
    $vehiculo = \App\Models\Vehiculo::factory()->create([
        'negocio_id' => auth()->user()->negocio_id,
        'precio_usd' => '9500.00',
    ]);

    return app(\App\Servicios\Ventas::class)->vender($vehiculo, array_merge([
        'comprador' => ['ci' => (string) fake()->unique()->numberBetween(1000000, 9999999), 'nombre' => 'Juan Carlos Mamani'],
        'tipo' => 'credito',
        'moneda' => 'USD',
        'regla_tc' => 'oficial',
        'precio' => '9500',
        'inicial' => '2500',
        'numero_cuotas' => 14,
        'monto_cuota' => '500',
        'primer_vencimiento' => today()->addMonth()->toDateString(),
        'fecha_venta' => today()->toDateString(),
    ], $cambios));
}

/** Crea un usuario con un rol de AutoNexo, en un negocio nuevo o en el indicado. */
function usuarioCon(string $rol, ?\App\Models\Negocio $negocio = null): \App\Models\User
{
    $user = \App\Models\User::factory()
        ->for($negocio ?? \App\Models\Negocio::factory())
        ->create();
    $user->assignRole($rol);

    return $user;
}
