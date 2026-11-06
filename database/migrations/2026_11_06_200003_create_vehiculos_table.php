<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PBI-03 (CA-03): vehículo identificado por chasis (VIN), único dentro de cada negocio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->string('chasis', 30);
            $table->string('placa', 15)->nullable();
            $table->string('marca', 60);
            $table->string('modelo', 60);
            $table->unsignedSmallInteger('anio');
            $table->string('color', 30)->nullable();
            $table->decimal('costo_usd', 14, 2);
            $table->decimal('precio_usd', 14, 2);
            $table->string('estado', 20)->default('en_exhibicion');
            $table->string('foto_path')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['negocio_id', 'chasis']);
            $table->index(['negocio_id', 'estado']);
            $table->index(['negocio_id', 'placa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
