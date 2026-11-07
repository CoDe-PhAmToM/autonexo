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
        'costo_usd', 'precio_usd', 'estado', 'observaciones',
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

    /** Máximo de fotos en la galería de un vehículo. */
    public const MAX_FOTOS = 8;

    public function fotos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VehiculoFoto::class)->orderBy('orden')->orderBy('id');
    }

    /** La primera foto de la galería, o null. */
    public function portada(): ?VehiculoFoto
    {
        return $this->relationLoaded('fotos') ? $this->fotos->first() : $this->fotos()->first();
    }

    /**
     * Agrega fotos a la galería (reducidas a 1600 px) y lo anota en el historial.
     *
     * @param  array<int, \Illuminate\Http\UploadedFile>  $archivos
     */
    public function agregarFotos(array $archivos): void
    {
        if ($archivos === []) {
            return;
        }

        $antes = $this->fotos()->count();
        $orden = (int) $this->fotos()->max('orden');

        foreach ($archivos as $archivo) {
            $this->fotos()->create([
                'negocio_id' => $this->negocio_id,
                'path' => \App\Support\Imagenes::guardar($archivo, 'vehiculos/'.$this->negocio_id),
                'orden' => ++$orden,
            ]);
        }

        $this->unsetRelation('fotos');
        $this->anotarEnBitacora('actualizado', ['fotos' => ['antes' => $antes, 'despues' => $antes + count($archivos)]]);
    }

    public function quitarFoto(VehiculoFoto $foto): void
    {
        abort_unless($foto->vehiculo_id === $this->id, 404);

        $antes = $this->fotos()->count();
        \Illuminate\Support\Facades\Storage::disk('local')->delete($foto->path);
        $foto->delete();

        $this->unsetRelation('fotos');
        $this->anotarEnBitacora('actualizado', ['fotos' => ['antes' => $antes, 'despues' => $antes - 1]]);
    }

    public function reservas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Reserva::class)->latest('id');
    }

    public function reservaActiva(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Reserva::class)->where('estado', 'activa');
    }

    public function venta(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Venta::class);
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
