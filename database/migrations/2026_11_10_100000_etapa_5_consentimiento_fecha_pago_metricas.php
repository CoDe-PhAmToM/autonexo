<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa 5:
 * - PBI-12: fecha en que el comprador dio su consentimiento para recibir comprobantes.
 * - PBI-23: fecha en que se recibió cada pago (para medir el registro "el mismo día")
 *   y la tabla de métricas de uso, sin datos personales (CA-23).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compradores', function (Blueprint $table) {
            $table->timestampTz('consentimiento_at')->nullable()->after('consentimiento_comprobante');
        });

        Schema::table('cobros', function (Blueprint $table) {
            $table->date('fecha_pago')->nullable()->after('medio');
        });
        DB::statement('UPDATE cobros SET fecha_pago = CAST(created_at AS date) WHERE fecha_pago IS NULL');

        Schema::create('metricas_uso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->string('evento', 30);                    // cartera_consultada | cobro_registrado
            $table->date('fecha');
            $table->decimal('segundos', 8, 2)->nullable();   // duración medida (null = sin dato)
            $table->boolean('mismo_dia')->nullable();        // cobro registrado el día que se recibió
            $table->timestampTz('created_at')->useCurrent();
            // Sin user_id ni datos del comprador: solo conteos y tiempos (CA-23).

            $table->index(['negocio_id', 'evento', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metricas_uso');
        Schema::table('cobros', fn (Blueprint $table) => $table->dropColumn('fecha_pago'));
        Schema::table('compradores', fn (Blueprint $table) => $table->dropColumn('consentimiento_at'));
    }
};
