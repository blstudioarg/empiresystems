<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('direccion')->nullable()->after('razon_social');
            $table->string('cp', 10)->nullable()->after('direccion');
            $table->string('ciudad')->nullable()->after('cp');
            $table->string('provincia')->nullable()->after('ciudad');
            $table->string('pais', 2)->default('ES')->after('provincia');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['direccion', 'cp', 'ciudad', 'provincia', 'pais']);
        });
    }
};
