<?php

namespace App\Models;

use App\Models\Concerns\PerteneceANegocio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PBI-11: repuesto con stock y kárdex de movimientos.
 * El stock solo cambia con mover(); nunca se edita a mano.
 */
class Repuesto extends Model
{
    /** @use HasFactory<\Database\Factories\RepuestoFactory> */
    use HasFactory, PerteneceANegocio;

    protected $fillable = ['negocio_id', 'codigo', 'nombre', 'unidad', 'stock_minimo', 'precio_bs', 'foto_path'];

    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'stock_minimo' => 'integer',
            'precio_bs' => 'decimal:2',
        ];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Kardex::class)->latest('id');
    }

    public function bajoMinimo(): bool
    {
        return $this->stock_minimo > 0 && $this->stock < $this->stock_minimo;
    }

    /**
     * Registra una entrada o salida y devuelve el movimiento del kárdex.
     * CA-11: una salida mayor al stock se rechaza y el stock no cambia.
     */
    public function mover(string $tipo, int $cantidad, ?string $detalle = null): Kardex
    {
        if (! in_array($tipo, ['entrada', 'salida'], true)) {
            throw new \InvalidArgumentException("Tipo de movimiento inválido: {$tipo}");
        }
        if ($cantidad < 1) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad debe ser al menos 1.']);
        }

        return DB::transaction(function () use ($tipo, $cantidad, $detalle) {
            // Bloquea la fila para que dos salidas a la vez no dejen el stock en negativo.
            $actual = static::whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            if ($tipo === 'salida' && $cantidad > $actual->stock) {
                throw ValidationException::withMessages([
                    'cantidad' => "No hay stock suficiente: quedan {$actual->stock} {$actual->unidad}(s).",
                ]);
            }

            $nuevo = $tipo === 'entrada' ? $actual->stock + $cantidad : $actual->stock - $cantidad;
            $actual->forceFill(['stock' => $nuevo])->save();
            $this->stock = $nuevo;

            return $this->movimientos()->create([
                'negocio_id' => $this->negocio_id,
                'user_id' => auth()->id(),
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'saldo' => $nuevo,
                'detalle' => $detalle,
            ]);
        });
    }
}
