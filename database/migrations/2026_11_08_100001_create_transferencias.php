<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PBI-10 (CA-10.1 a 10.3): lista de control de la transferencia de propiedad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('venta_id')->unique()->constrained('ventas');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_entrega');
            $table->date('fecha_minuta');
            $table->date('fecha_limite_imt');            // fecha de la minuta + plazo en días hábiles
            $table->string('estado', 12)->default('en_curso'); // en_curso | concluida
            $table->timestampTz('concluida_at')->nullable();
            $table->timestamps();

            $table->index(['negocio_id', 'estado']);
        });

        Schema::create('transferencia_pasos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transferencia_id')->constrained('transferencias')->cascadeOnDelete();
            $table->unsignedTinyInteger('orden');
            $table->string('clave', 20);                 // minuta | imt | notaria | registro | transito
            $table->string('nombre', 80);
            $table->date('fecha')->nullable();           // cuándo se cumplió
            $table->string('responsable', 120)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['transferencia_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencia_pasos');
        Schema::dropIfExists('transferencias');
    }
};
