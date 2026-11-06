<?php

namespace App\Importacion;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** PBI-13: lee las dos hojas de la plantilla como filas con encabezado. */
class LectorCarga implements Import, SkipsUnknownSheets, WithMultipleSheets
{
    public function sheets(): array
    {
        $hoja = new class implements Import, WithHeadingRow {};

        return [
            PlantillaCarga::HOJA_VEHICULOS => $hoja,
            PlantillaCarga::HOJA_VENTAS => $hoja,
        ];
    }

    public function onUnknownSheet(string|int $sheetName): void
    {
        // Si falta una hoja, simplemente no se carga nada de ella.
    }
}
