<?php

namespace App\Importacion;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Una hoja de la plantilla: encabezados y filas. */
class HojaPlantilla implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private string $titulo, private array $columnas, private array $filas)
    {
    }

    public function array(): array
    {
        return $this->filas;
    }

    public function headings(): array
    {
        return $this->columnas;
    }

    public function title(): string
    {
        return $this->titulo;
    }
}
