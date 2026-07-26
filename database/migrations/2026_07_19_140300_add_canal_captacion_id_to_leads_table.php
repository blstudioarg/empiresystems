<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('canal_captacion_id')->nullable()->after('origen')
                ->constrained('canales_captacion')->nullOnDelete();

            $table->index(['tenant_id', 'canal_captacion_id']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'canal_captacion_id']);
            $table->dropConstrainedForeignId('canal_captacion_id');
        });
    }
};
