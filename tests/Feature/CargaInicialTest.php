<?php

/**
 * PBI-13 — CA-13: cargar desde la plantilla vehículos y ventas a crédito vigentes;
 * detectar chasis duplicados y rechazar filas inválidas sin perder las válidas.
 */

use App\Importacion\PlantillaCarga;
use App\Models\Cobro;
use App\Models\Venta;
use App\Models\Vehiculo;
use App\Support\Roles;
use Illuminate\Http\UploadedFile;
use Livewire\Volt\Volt;
use Maatwebsite\Excel\Excel as ExcelTipos;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->dueno = usuarioCon(Roles::DUENO);
    $this->actingAs($this->dueno);
});

function archivoDeCarga(array $vehiculos, array $ventas = []): UploadedFile
{
    $contenido = Excel::raw(new PlantillaCarga($vehiculos, $ventas), ExcelTipos::XLSX);

    return UploadedFile::fake()->createWithContent('carga.xlsx', $contenido);
}

test('la plantilla se descarga con sus dos hojas', function () {
    $this->get(route('carga.plantilla'))
        ->assertOk()
        ->assertDownload('plantilla_autonexo.xlsx');
});

test('CA-13: carga vehículos y ventas vigentes; las filas inválidas se reportan y las válidas se guardan', function () {
    Vehiculo::factory()->for($this->dueno->negocio)->create(['chasis' => 'YAEXISTE0000001']);

    $archivo = archivoDeCarga([
        ['JTDBR32E720123456', '2345ABC', 'Toyota', 'Corolla', 2018, 'Blanco', '8500.00', '9500.00'],     // fila 2: válida
        ['MR0FZ22G900222222', '4567XYZ', 'Toyota', 'Hilux', 2020, 'Plata', 15000, 17500.5],            // fila 3: válida (números)
        ['YAEXISTE0000001', '1111AAA', 'Suzuki', 'Swift', 2019, 'Rojo', '6000', '7000'],               // fila 4: chasis duplicado (base)
        ['JTDBR32E-720123456', '9999ZZZ', 'Toyota', 'Corolla', 2018, 'Negro', '8500', '9500'],         // fila 5: duplicado en el archivo
        ['JS2ZC21S000333333', '', '', 'Vitara', 2030, '', 'abc', '9000'],                              // fila 6: varios errores
    ], [
        ['JTDBR32E720123456', '6045123', 'Juan Carlos Mamani', '71234567', '2026-06-05', 'USD', '9500.00', '2500.00', 14, '2026-07-05', 4], // válida
        ['NOEXISTE00000000', '7012456', 'María Quispe', '', '05/06/2026', 'USD', '9000', '3000', 12, '05/07/2026', 0],                      // chasis inexistente
        ['MR0FZ22G900222222', '4987321', 'Pedro Limachi', '', '2026-06-05', 'USD', '17500.50', '5000', 10, '2026-07-05', 10],               // todas pagadas
    ]);

    Volt::test('carga.index')->set('archivo', $archivo)->call('importar')->assertHasNoErrors()
        ->assertSee('Filas que no se cargaron');

    // Vehículos: 2 nuevos + el que ya existía.
    expect(Vehiculo::count())->toBe(3)
        ->and(Vehiculo::where('chasis', 'MR0FZ22G900222222')->value('precio_usd'))->toBe('17500.50');

    // Venta válida con 4 cuotas ya pagadas por un cobro de carga inicial.
    $venta = Venta::with('cuotas')->sole();
    expect($venta->cuotas)->toHaveCount(14)
        ->and($venta->cuotas->take(4)->every(fn ($c) => $c->estado() === 'pagada'))->toBeTrue()
        ->and($venta->cuotas[4]->pagado)->toBe('0.00')
        ->and($venta->saldo())->toBe('5000.00')
        ->and(Cobro::sole()->medio)->toBe('carga_inicial')
        ->and($venta->vehiculo->estado)->toBe('vendido');
});

test('el resultado indica qué filas fallaron y por qué', function () {
    Vehiculo::factory()->for($this->dueno->negocio)->create(['chasis' => 'YAEXISTE0000001']);

    $resultado = app(\App\Servicios\CargaInicial::class)->procesar(archivoDeCarga([
        ['YAEXISTE0000001', '', 'Suzuki', 'Swift', 2019, '', '6000', '7000'],
        ['OTROCHASIS12345', '', 'Suzuki', 'Swift', 2019, '', '6000', '7000'],
    ]));

    expect($resultado['vehiculos'])->toBe(1)
        ->and($resultado['errores'])->toHaveCount(1)
        ->and($resultado['errores'][0])->toMatchArray(['hoja' => 'Vehiculos', 'fila' => 2])
        ->and($resultado['errores'][0]['mensaje'])->toContain('YAEXISTE0000001 ya está registrado');
});

test('solo el dueño puede cargar datos', function (string $rol) {
    $this->actingAs(usuarioCon($rol))->get(route('carga.index'))->assertForbidden();
})->with([Roles::VENDEDOR, Roles::CAJERO, Roles::ALMACENERO]);
