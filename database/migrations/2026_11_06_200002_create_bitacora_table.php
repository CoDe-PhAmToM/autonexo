<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PBI-20 (CA-20) y PBI-03 (CA-03): bitácora de auditoría.
 * Guarda quién hizo qué, cuándo y qué cambió. Sus filas nunca se modifican ni se borran:
 * además de la regla en el modelo, un trigger de PostgreSQL lo impide en la propia base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->nullable()->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->morphs('auditable');
            $table->string('accion', 20);          // creado | actualizado | anulado
            $table->text('motivo')->nullable();
            $table->jsonb('cambios')->nullable();  // {"placa": {"antes": "...", "despues": "..."}}
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION bitacora_inmutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'La bitácora no se modifica ni se borra';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER bitacora_sin_cambios
                BEFORE UPDATE OR DELETE ON bitacora
                FOR EACH ROW EXECUTE FUNCTION bitacora_inmutable();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
        DB::unprepared('DROP FUNCTION IF EXISTS bitacora_inmutable()');
    }
};
