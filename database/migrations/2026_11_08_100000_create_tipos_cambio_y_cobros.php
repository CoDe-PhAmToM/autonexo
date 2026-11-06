<?php

use App\Support\Roles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * PBI-07 (CA-07.1 a 07.4): tipo de cambio del día y cobros en Bs o USD, en efectivo o QR.
 * Un cobro nunca se edita ni se borra (PBI-20): solo se anula con motivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_cambio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->date('fecha');
            $table->decimal('valor', 10, 4);          // Bs por 1 USD
            $table->string('fuente', 60)->default('Oficial');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['negocio_id', 'fecha']);
        });

        Schema::create('cobros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negocio_id')->constrained('negocios')->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained('ventas');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('medio', 15);                    // efectivo | qr | carga_inicial
            $table->string('moneda_pago', 3);               // USD | BOB
            $table->decimal('monto_pago', 14, 2);           // lo que entregó el comprador
            $table->decimal('tc_aplicado', 10, 4)->nullable();
            $table->string('fuente_tc', 120)->nullable();
            $table->string('motivo_tc')->nullable();        // si se cambió el TC oficial (HP-02)
            $table->decimal('monto_aplicado', 14, 2);       // en la moneda de la venta
            $table->string('estado', 15);                   // confirmado | por_confirmar
            $table->string('comprobante_path')->nullable(); // captura del QR
            $table->foreignId('confirmado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('confirmado_at')->nullable();
            $table->timestampTz('anulado_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();

            $table->index(['negocio_id', 'created_at']);
            $table->index(['negocio_id', 'estado']);
        });

        // Cómo se repartió cada cobro entre las cuotas (sirve para recalcular al anular o confirmar).
        Schema::create('cobro_cuota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cobro_id')->constrained('cobros')->cascadeOnDelete();
            $table->foreignId('cuota_id')->constrained('cuotas')->cascadeOnDelete();
            $table->decimal('monto', 14, 2);

            $table->unique(['cobro_id', 'cuota_id']);
        });

        DB::statement("ALTER TABLE cobros ADD CONSTRAINT cobros_estado_valido CHECK (estado IN ('confirmado', 'por_confirmar'))");
        DB::statement('ALTER TABLE cobros ADD CONSTRAINT cobros_monto_positivo CHECK (monto_pago > 0 AND monto_aplicado > 0)');

        // PBI-10: plazo del IMT configurable por negocio (días hábiles).
        Schema::table('negocios', function (Blueprint $table) {
            $table->unsignedSmallInteger('plazo_imt_dias')->default(10);
        });

        // Permisos nuevos de esta etapa (cobros.confirmar, datos.importar).
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (array_keys(Roles::PERMISOS) as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }
        foreach (Roles::MATRIZ as $rol => $permisos) {
            Role::findOrCreate($rol, 'web')->syncPermissions($permisos);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('negocios', fn (Blueprint $table) => $table->dropColumn('plazo_imt_dias'));
        Schema::dropIfExists('cobro_cuota');
        Schema::dropIfExists('cobros');
        Schema::dropIfExists('tipos_cambio');
    }
};
