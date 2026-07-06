<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 022 — recepción electrónica de facturas de proveedor. Columnas ya documentadas en
 * docs/03-modelo-datos.md pero aún no materializadas (research R7). Se añaden nullable/con default
 * para no romper las compras ya existentes (manuales).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->string('origen', 20)->default('manual')->after('estado');
            $table->string('formato_recepcion', 20)->nullable()->after('origen');
            $table->string('archivo_recibido_path')->nullable()->after('formato_recepcion');
            $table->string('estado_b2b', 20)->nullable()->after('archivo_recibido_path');
            $table->dateTime('estado_b2b_fecha')->nullable()->after('estado_b2b');

            $table->index(['tenant_id', 'estado_b2b']);
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'estado_b2b']);
            $table->dropColumn([
                'origen',
                'formato_recepcion',
                'archivo_recibido_path',
                'estado_b2b',
                'estado_b2b_fecha',
            ]);
        });
    }
};
