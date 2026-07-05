<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logs_actividad', function (Blueprint $table) {
            $table->string('resultado', 10)->default('exito')->after('accion');
            $table->string('ip_origen', 45)->nullable()->after('resultado');
            $table->string('user_agent', 255)->nullable()->after('ip_origen');

            $table->index(['tenant_id', 'resultado']);
        });
    }

    public function down(): void
    {
        Schema::table('logs_actividad', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'resultado']);
            $table->dropColumn(['resultado', 'ip_origen', 'user_agent']);
        });
    }
};
