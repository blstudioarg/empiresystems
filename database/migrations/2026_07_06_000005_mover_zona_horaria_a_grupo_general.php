<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La zona horaria dejó de ser una config de fichajes y pasó a ser global del tenant: las filas
 * `configuraciones` existentes (`clave = 'fichajes.zona_horaria'`, `grupo = 'fichajes'`) se
 * promueven a `general.zona_horaria` / `grupo = 'general'`, preservando el valor elegido por cada
 * tenant. Multi-tenant: se actualizan todas las filas por clave, sin filtrar por tenant, porque la
 * clave es única por tenant y el cambio es idéntico para todos.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuraciones')
            ->where('clave', 'fichajes.zona_horaria')
            ->update([
                'clave' => 'general.zona_horaria',
                'grupo' => 'general',
            ]);
    }

    public function down(): void
    {
        DB::table('configuraciones')
            ->where('clave', 'general.zona_horaria')
            ->update([
                'clave' => 'fichajes.zona_horaria',
                'grupo' => 'fichajes',
            ]);
    }
};
