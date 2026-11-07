<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Galería de fotos por vehículo (antes era una sola foto) y foto opcional por repuesto.
 * La foto que ya tenía cada vehículo pasa a ser la primera de su galería.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculo_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['vehiculo_id', 'orden']);
        });

        DB::statement(<<<'SQL'
            INSERT INTO vehiculo_fotos (negocio_id, vehiculo_id, path, orden, created_at, updated_at)
            SELECT negocio_id, id, foto_path, 0, NOW(), NOW() FROM vehiculos WHERE foto_path IS NOT NULL
        SQL);

        Schema::table('vehiculos', fn (Blueprint $table) => $table->dropColumn('foto_path'));

        Schema::table('repuestos', function (Blueprint $table) {
            $table->string('foto_path')->nullable()->after('precio_bs');
        });
    }

    public function down(): void
    {
        Schema::table('repuestos', fn (Blueprint $table) => $table->dropColumn('foto_path'));

        Schema::table('vehiculos', function (Blueprint $table) {
            $table->string('foto_path')->nullable();
        });
        DB::statement(<<<'SQL'
            UPDATE vehiculos v SET foto_path = (
                SELECT path FROM vehiculo_fotos f WHERE f.vehiculo_id = v.id ORDER BY orden, id LIMIT 1
            )
        SQL);

        Schema::dropIfExists('vehiculo_fotos');
    }
};
