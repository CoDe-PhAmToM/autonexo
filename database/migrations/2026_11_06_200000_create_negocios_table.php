<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PBI-02: cada comercializadora es un "negocio". Todos sus datos cuelgan de él.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('negocios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('nit', 20)->nullable();
            $table->string('ciudad', 60)->default('El Alto');
            $table->string('telefono', 20)->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('negocio_id')->nullable()->after('id')->constrained('negocios')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('negocio_id');
        });

        Schema::dropIfExists('negocios');
    }
};
