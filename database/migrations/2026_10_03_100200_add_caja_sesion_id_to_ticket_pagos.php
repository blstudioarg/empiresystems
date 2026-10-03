<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 048 — atribución de cada cobro del TPV a su sesión de caja (research D1).
 *
 * Va en `ticket_pagos` (desglose **interno** de cómo se cobró en caja) y no en `facturas`: la
 * tabla fiscal no se toca. Nullable y sin backfill: los tickets anteriores a la caja no pertenecen
 * a ninguna sesión y no se reconstruyen sesiones históricas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_pagos', function (Blueprint $table) {
            $table->foreignId('caja_sesion_id')->nullable()->after('factura_id')
                ->constrained('caja_sesiones')->nullOnDelete();

            $table->index(['tenant_id', 'caja_sesion_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_pagos', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'caja_sesion_id']);
            $table->dropConstrainedForeignId('caja_sesion_id');
        });
    }
};
