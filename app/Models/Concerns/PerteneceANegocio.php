<?php

namespace App\Models\Concerns;

use App\Models\Negocio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RNF-04 (aislamiento): un usuario solo ve y crea datos de su propio negocio.
 * Se agrega a todos los modelos con columna negocio_id (menos User).
 */
trait PerteneceANegocio
{
    public static function bootPerteneceANegocio(): void
    {
        static::addGlobalScope('negocio', function (Builder $query) {
            if (auth()->check()) {
                $query->where($query->getModel()->getTable().'.negocio_id', auth()->user()->negocio_id);
            }
        });

        static::creating(function ($modelo) {
            if (auth()->check() && empty($modelo->negocio_id)) {
                $modelo->negocio_id = auth()->user()->negocio_id;
            }
        });
    }

    public function negocio(): BelongsTo
    {
        return $this->belongsTo(Negocio::class);
    }
}
