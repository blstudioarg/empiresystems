<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las facturas simplificadas (tickets) pueden no tener receptor (variante simple), por lo que
 * `cliente_id` debe poder quedar nulo. El resto del snapshot `cliente_*` ya era nullable; sólo
 * la FK `cliente_id` estaba como NOT NULL. Cambio aditivo: las filas existentes ya tienen valor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable(false)->change();
        });
    }
};
