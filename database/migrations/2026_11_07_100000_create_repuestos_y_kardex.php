<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PBI-11 (CA-11): kárdex de repuestos. Las entradas y salidas actualizan el saldo,
 * una salida nunca deja stock negativo y se alerta cuando el stock queda bajo el mínimo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->string('codigo', 30);
            $table->string('nombre');
            $table->string('unidad', 20)->default('pieza');
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('stock_minimo')->default(0);
            $table->decimal('precio_bs', 14, 2)->nullable();
            $table->timestamps();

            $table->unique(['negocio_id', 'codigo']);
        });

        Schema::create('kardex', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('repuesto_id')->constrained('repuestos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 10);                 // entrada | salida
            $table->unsignedInteger('cantidad');
            $table->unsignedInteger('saldo');           // stock después del movimiento
            $table->string('detalle')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['repuesto_id', 'id']);
        });

        // Segunda barrera: la base rechaza un stock negativo aunque falle la validación.
        DB::statement('ALTER TABLE repuestos ADD CONSTRAINT repuestos_stock_no_negativo CHECK (stock >= 0)');
        DB::statement("ALTER TABLE kardex ADD CONSTRAINT kardex_tipo_valido CHECK (tipo IN ('entrada', 'salida'))");
        DB::statement('ALTER TABLE kardex ADD CONSTRAINT kardex_cantidad_positiva CHECK (cantidad > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('kardex');
        Schema::dropIfExists('repuestos');
    }
};
