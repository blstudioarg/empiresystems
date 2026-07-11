<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factura_lineas', function (Blueprint $table) {
            $table->foreignId('albaran_id')->nullable()->after('articulo_id')->constrained('albaranes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('factura_lineas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('albaran_id');
        });
    }
};
