<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PBI-05 (reservas) y PBI-06 (ventas y plan de cuotas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compradores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('ci', 20);
            $table->string('telefono', 20)->nullable();
            $table->boolean('consentimiento_comprobante')->default(false); // PBI-12
            $table->timestamps();

            $table->unique(['negocio_id', 'ci']);
        });

        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->cascadeOnDelete();
            $table->foreignId('comprador_id')->constrained('compradores');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('anticipo', 14, 2);
            $table->string('moneda', 3)->default('USD');   // USD | BOB
            $table->date('fecha_limite');
            $table->string('estado', 12)->default('activa'); // activa | vencida | convertida | cancelada
            $table->timestamps();

            $table->index(['negocio_id', 'estado', 'fecha_limite']);
        });

        // Un vehículo tiene como máximo una reserva activa.
        DB::statement("CREATE UNIQUE INDEX reservas_una_activa_por_vehiculo ON reservas (vehiculo_id) WHERE estado = 'activa'");

        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('vehiculo_id')->unique()->constrained('vehiculos');
            $table->foreignId('comprador_id')->constrained('compradores');
            $table->foreignId('reserva_id')->nullable()->constrained('reservas');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 10);                   // contado | credito
            $table->string('moneda', 3)->default('USD');  // moneda de referencia: USD | BOB
            $table->string('regla_tc', 10)->default('oficial'); // oficial (del día del pago) | pactado
            $table->decimal('tc_pactado', 10, 4)->nullable();
            $table->decimal('precio', 14, 2);
            $table->decimal('inicial', 14, 2);
            $table->unsignedSmallInteger('numero_cuotas')->default(0);
            $table->date('fecha_venta');
            $table->timestamps();
        });

        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->date('vencimiento');
            $table->decimal('monto', 14, 2);
            $table->decimal('pagado', 14, 2)->default(0); // lo actualizan los cobros (PBI-07)
            $table->timestamps();

            $table->unique(['venta_id', 'numero']);
            $table->index(['negocio_id', 'vencimiento']);
        });

        DB::statement('ALTER TABLE cuotas ADD CONSTRAINT cuotas_pagado_valido CHECK (pagado >= 0 AND pagado <= monto)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cuotas');
        Schema::dropIfExists('ventas');
        Schema::dropIfExists('reservas');
        Schema::dropIfExists('compradores');
    }
};
