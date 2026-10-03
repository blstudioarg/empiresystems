<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `users.email` pasa de único GLOBAL a único POR TENANT.
 *
 * El índice `users_email_unique` venía de la migración original de Laravel
 * (`0001_01_01_000000_create_users_table`), anterior al multi-tenant. Cuando
 * `add_tenancy_fields_to_users_table` añadió `tenant_id` no se tocó ese índice, así que en toda
 * la plataforma no podía existir el mismo correo dos veces aunque fueran empresas distintas.
 *
 * Consecuencia real: dar de alta un tenant cuyo administrador ya era usuario de otro tenant
 * reventaba con "Duplicate entry ... for key 'users_email_unique'" y el alta entera hacía
 * rollback. Una misma persona tampoco podía ser usuaria de dos empresas, que es justo lo que un
 * SaaS multi-tenant tiene que permitir.
 *
 * El resto del código ya asumía unicidad por tenant (ver
 * `Profile\SolicitarCambioEmailRequest`: "Ese correo ya está en uso en tu empresa"), así que
 * esto alinea el esquema con la regla que la aplicación ya aplicaba.
 *
 * El super admin tiene `tenant_id` NULL. En MySQL un índice único ignora las filas donde alguna
 * columna del índice es NULL, así que varios super admins podrían repetir correo; eso se cubre
 * en validación (`Rule::unique` sin tenant cuando no hay tenant), no en el índice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->unique(['tenant_id', 'email'], 'users_tenant_email_unique');
        });
    }

    public function down(): void
    {
        // Volver atrás solo es posible si no hay correos repetidos entre tenants; si los hay,
        // MySQL rechazará el índice y habrá que resolverlos a mano antes.
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_tenant_email_unique');
            $table->unique('email', 'users_email_unique');
        });
    }
};
