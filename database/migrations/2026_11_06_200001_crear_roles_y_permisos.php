<?php

use App\Support\Roles;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * PBI-02: los 4 roles del Entregable (dueño, cajero, vendedor, almacenero) y sus permisos.
 * Va en una migración para que existan siempre, también en la base de pruebas.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Roles::PERMISOS as $permiso => $descripcion) {
            Permission::findOrCreate($permiso, 'web');
        }

        foreach (Roles::MATRIZ as $rol => $permisos) {
            Role::findOrCreate($rol, 'web')->syncPermissions($permisos);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->delete();
        Permission::query()->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
