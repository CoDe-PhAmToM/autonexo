<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use App\Models\Concerns\RegistraBitacora;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * PBI-03: vehículo identificado por chasis y placa, con costo y precio en dólares.
 */
class Vehiculo extends Model
{
    /** @use HasFactory<\Database\Factories\VehiculoFactory> */
    use HasFactory, PerteneceANegocio, RegistraBitacora;

    public const ESTADOS = [
        'en_exhibicion' => 'En exhibición',
        'reservado' => 'Reservado',
        'vendido' => 'Vendido',
        'entregado' => 'Entregado',
    ];

    public const COLORES_ESTADO = [
        'en_exhibicion' => 'green',
        'reservado' => 'amber',
        'vendido' => 'blue',
        'entregado' => 'zinc',
    ];

    protected $fillable = [
        'negocio_id', 'chasis', 'placa', 'marca', 'modelo', 'anio', 'color',
        'costo_usd', 'precio_usd', 'estado', 'foto_path', 'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'costo_usd' => 'decimal:2',
            'precio_usd' => 'decimal:2',
        ];
    }

    /** El chasis se guarda sin espacios y en mayúsculas, para detectar duplicados. */
    protected function chasis(): Attribute
    {
        return Attribute::set(fn (?string $valor) => self::normalizarChasis($valor));
    }

    protected function placa(): Attribute
    {
        return Attribute::set(fn (?string $valor) => $valor === null || trim($valor) === ''
            ? null
            : mb_strtoupper(preg_replace('/\s+/', '', $valor)));
    }

    public static function normalizarChasis(?string $valor): string
    {
        return mb_strtoupper(preg_replace('/[\s\-]+/', '', (string) $valor));
    }

    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function colorEstado(): string
    {
        return self::COLORES_ESTADO[$this->estado] ?? 'zinc';
    }

    public function descripcion(): string
    {
        return "{$this->marca} {$this->modelo} {$this->anio}";
    }
}
