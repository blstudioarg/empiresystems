<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::table('oportunidades', function (Blueprint $table) {
            $table->index(['tenant_id', 'cerrada_at']);
        });

        Schema::table('presupuestos', function (Blueprint $table) {
            $table->index(['tenant_id', 'fecha_emision']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'created_at']);
        });

        Schema::table('oportunidades', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'cerrada_at']);
        });

        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'fecha_emision']);
        });
    }
};
