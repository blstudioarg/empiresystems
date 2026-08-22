<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice de soporte para el módulo de Cobros (feature 043, T053/SC-004): ConsultaCobros filtra
 * por "solo vencidas", calcula días de retraso y ordena por fecha_vencimiento por defecto sobre
 * miles de facturas por tenant. `facturas` ya tenía índices en (tenant_id, estado) y
 * (tenant_id, fecha_expedicion) pero no en fecha_vencimiento — puramente aditivo, no toca datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->index(['tenant_id', 'fecha_vencimiento']);
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'fecha_vencimiento']);
        });
    }
};
