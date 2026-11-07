<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PBI-22 (CA-22): cierre de caja diario por cajero.
 * PBI-21 (CA-21): registro de cada respaldo, con su resultado (los fallidos quedan visibles).
 */
return new class extends Migration
{
    /** Combinaciones medio_moneda que se cuadran. */
    private const COMBOS = ['efectivo_bob', 'efectivo_usd', 'qr_bob', 'qr_usd'];

    public function up(): void
    {
        Schema::create('cierres_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->date('fecha');
            foreach (self::COMBOS as $combo) {
                $table->decimal("{$combo}_registrado", 14, 2)->default(0);
                $table->decimal("{$combo}_declarado", 14, 2)->default(0);
                $table->decimal("{$combo}_diferencia", 14, 2)->default(0); // declarado - registrado
            }
            $table->unsignedInteger('cobros')->default(0);
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['negocio_id', 'user_id', 'fecha']);
        });

        Schema::create('respaldos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 10);              // exitoso | fallido
            $table->string('archivo')->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->text('mensaje')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respaldos');
        Schema::dropIfExists('cierres_caja');
    }
};
