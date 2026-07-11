<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('albaran_lineas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('albaran_id')->constrained('albaranes')->cascadeOnDelete();
            $table->foreignId('presupuesto_linea_id')->nullable()->constrained('presupuesto_lineas')->nullOnDelete();
            $table->foreignId('articulo_id')->nullable()->constrained('articulos')->nullOnDelete();
            $table->string('concepto');
            $table->string('unidad', 20)->nullable();
            $table->decimal('cantidad', 12, 4);
            $table->decimal('precio_unitario', 12, 4);
            $table->decimal('descuento_porcentaje', 5, 2)->nullable();
            $table->decimal('base', 12, 2)->default(0);
            $table->decimal('tipo_impositivo', 5, 2);
            $table->decimal('cuota_impuesto', 12, 2)->default(0);
            $table->decimal('tipo_recargo', 5, 2)->nullable();
            $table->decimal('cuota_recargo', 12, 2)->default(0);
            $table->smallInteger('orden')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'albaran_id']);
            $table->index(['tenant_id', 'presupuesto_linea_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('albaran_lineas');
    }
};
