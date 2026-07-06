<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->date('referencia_fecha')->nullable()->after('fichaje_id');
        });

        Schema::table('alertas', function (Blueprint $table) {
            $table->foreignId('fichaje_id')->nullable()->change();
            $table->unsignedInteger('distancia_metros')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->dropColumn('referencia_fecha');
        });
    }
};
