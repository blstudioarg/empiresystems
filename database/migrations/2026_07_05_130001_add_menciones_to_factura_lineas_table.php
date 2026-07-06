<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 022 — menciones especiales por línea (docs/02-facturacion-espana.md §6). Columnas ya
 * documentadas en docs/03-modelo-datos.md pero aún no materializadas en el esquema (research R7).
 * Se añaden nullable/con default para no romper las líneas ya existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factura_lineas', function (Blueprint $table) {
            $table->string('calificacion_operacion', 2)->default('S1')->after('cuota_recargo');
            $table->string('causa_exencion', 2)->nullable()->after('calificacion_operacion');
            $table->string('mencion_legal', 255)->nullable()->after('causa_exencion');
        });
    }

    public function down(): void
    {
        Schema::table('factura_lineas', function (Blueprint $table) {
            $table->dropColumn(['calificacion_operacion', 'causa_exencion', 'mencion_legal']);
        });
    }
};
