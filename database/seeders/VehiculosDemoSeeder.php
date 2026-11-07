<?php

namespace Database\Seeders;

use App\Models\Negocio;
use App\Models\Vehiculo;
use App\Models\VehiculoFoto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stock de demostración: 12 vehículos fijos. Cada foto corresponde a su modelo
 * (fotos libres de Unsplash, ver README); el Nissan Frontier y el Kia Rio quedan sin foto.
 */
class VehiculosDemoSeeder extends Seeder
{
    /** [marca, modelo, año, color, costo USD, precio USD, placa, foto] */
    private const STOCK = [
        // Los dos sin foto van primero: la vitrina muestra los más recientes arriba.
        ['Nissan', 'Frontier', 2016, 'Gris', '18500.00', '21400.00', '3650HTU', null],
        ['Kia', 'Rio', 2017, 'Negro', '9800.00', '11500.00', '3812HXA', null],
        ['Toyota', 'Corolla', 2020, 'Blanco', '15800.00', '17900.00', '4512KTR', 'toyota-corolla.jpg'],
        ['Volkswagen', 'Amarok', 2018, 'Azul', '27500.00', '31200.00', '3987JLM', 'volkswagen-amarok.jpg'],
        ['Hyundai', 'Tucson', 2023, 'Gris', '29900.00', '33500.00', '5120LPD', 'hyundai-tucson.jpg'],
        ['Suzuki', 'Swift', 2019, 'Azul', '11500.00', '13400.00', '4210KAZ', 'suzuki-swift.jpg'],
        ['Kia', 'Sportage', 2023, 'Gris oscuro', '28700.00', '32300.00', '5098LMN', 'kia-sportage.jpg'],
        ['Toyota', 'Hilux', 2021, 'Blanco', '31000.00', '35500.00', '4877KXY', 'toyota-hilux.jpg'],
        ['Mitsubishi', 'L200', 2004, 'Rojo y plata', '7800.00', '9400.00', '2315DFR', 'mitsubishi-l200.jpg'],
        ['Toyota', 'RAV4', 2019, 'Plata', '24500.00', '27800.00', '4402KHB', 'toyota-rav4.jpg'],
        ['Suzuki', 'Grand Vitara', 2005, 'Plata', '8900.00', '10600.00', '2768DGS', 'suzuki-vitara.jpg'],
        ['Hyundai', 'Accent', 2019, 'Blanco', '12300.00', '14200.00', '4355KCL', 'hyundai-accent.jpg'],
    ];

    public function run(): void
    {
        $negocio = Negocio::where('nombre', 'Automotores Illimani (demo)')->firstOrFail();

        if (Vehiculo::withoutGlobalScopes()->where('negocio_id', $negocio->id)->exists()) {
            return;
        }

        mt_srand(2026);
        foreach (self::STOCK as [$marca, $modelo, $anio, $color, $costo, $precio, $placa, $foto]) {
            $vehiculo = Vehiculo::create([
                'negocio_id' => $negocio->id,
                'chasis' => self::chasis(),
                'placa' => $placa,
                'marca' => $marca,
                'modelo' => $modelo,
                'anio' => $anio,
                'color' => $color,
                'costo_usd' => $costo,
                'precio_usd' => $precio,
                'estado' => 'en_exhibicion',
            ]);

            $origen = database_path('seeders/fotos/vehiculos/'.$foto);
            if ($foto && is_file($origen)) {
                $ruta = "vehiculos/{$negocio->id}/".Str::uuid().'.jpg';
                Storage::disk('local')->put($ruta, file_get_contents($origen));
                VehiculoFoto::create(['negocio_id' => $negocio->id, 'vehiculo_id' => $vehiculo->id, 'path' => $ruta, 'orden' => 1]);
            }
        }
    }

    /** Chasis de 17 caracteres con el formato de un VIN (sin I, O ni Q). */
    private static function chasis(): string
    {
        $letras = 'ABCDEFGHJKLMNPRSTUVWXYZ0123456789';
        $vin = '';
        for ($i = 0; $i < 17; $i++) {
            $vin .= $letras[mt_rand(0, strlen($letras) - 1)];
        }

        return $vin;
    }
}
