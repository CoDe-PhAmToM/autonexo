<?php

namespace App\Importacion;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * PBI-13: plantilla de carga inicial con dos hojas.
 * Sin argumentos genera la plantilla con una fila de ejemplo en cada hoja.
 */
class PlantillaCarga implements Export, WithMultipleSheets
{
    public const HOJA_VEHICULOS = 'Vehiculos';
    public const HOJA_VENTAS = 'Ventas a credito';

    public const COLUMNAS_VEHICULOS = ['chasis', 'placa', 'marca', 'modelo', 'anio', 'color', 'costo_usd', 'precio_usd'];

    public const COLUMNAS_VENTAS = [
        'chasis', 'ci_comprador', 'nombre_comprador', 'telefono', 'fecha_venta', 'moneda',
        'precio', 'inicial', 'numero_cuotas', 'primer_vencimiento', 'cuotas_pagadas',
    ];

    public function __construct(private ?array $vehiculos = null, private ?array $ventas = null)
    {
    }

    public function sheets(): array
    {
        return [
            new HojaPlantilla(self::HOJA_VEHICULOS, self::COLUMNAS_VEHICULOS, $this->vehiculos ?? [
                ['JTDBR32E720123456', '2345ABC', 'Toyota', 'Corolla', 2018, 'Blanco', '8500.00', '9500.00'],
            ]),
            new HojaPlantilla(self::HOJA_VENTAS, self::COLUMNAS_VENTAS, $this->ventas ?? [
                ['JTDBR32E720123456', '6045123', 'Juan Carlos Mamani', '71234567', '2026-06-05', 'USD', '9500.00', '2500.00', 14, '2026-07-05', 4],
            ]),
        ];
    }
}
