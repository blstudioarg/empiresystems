<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->string('verifactu_entorno', 12)->nullable()->after('registrada_at');
            $table->index(['tenant_id', 'registrada_at', 'id'], 'facturas_verifactu_cadena_index');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropIndex('facturas_verifactu_cadena_index');
            $table->dropColumn('verifactu_entorno');
        });
    }
};
