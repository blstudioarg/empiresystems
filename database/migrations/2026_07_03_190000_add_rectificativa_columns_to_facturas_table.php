<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->boolean('es_rectificativa')->default(false)->after('tipo');
            $table->foreignId('factura_rectificada_id')->nullable()->after('es_rectificativa')
                ->constrained('facturas')->nullOnDelete();
            $table->text('motivo_rectificacion')->nullable()->after('factura_rectificada_id');
            $table->string('tipo_rectificacion')->nullable()->after('motivo_rectificacion');

            $table->index(['tenant_id', 'factura_rectificada_id']);
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'factura_rectificada_id']);
            $table->dropConstrainedForeignId('factura_rectificada_id');
            $table->dropColumn(['es_rectificativa', 'motivo_rectificacion', 'tipo_rectificacion']);
        });
    }
};
