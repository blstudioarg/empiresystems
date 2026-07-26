<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migración de datos (doc 09, Cambio 4): elimina el permiso fantasma ver-bancos. Bancos y cuentas
 * bancarias no son vistas de menú: se administran embebidos en Configuración → Facturación, y sus
 * rutas pasaron a gatearse con ver-configuracion. No hay que conceder nada a cambio: quien
 * administra bancos entra por Configuración. Borrar el permiso cascada a role_has_permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::where('name', 'ver-bancos')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Migración de datos: no reversible (el permiso ya no existe en el catálogo).
    }
};
