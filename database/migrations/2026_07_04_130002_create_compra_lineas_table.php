<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compra_lineas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('articulo_id')->nullable()->constrained('articulos')->nullOnDelete();
            $table->string('concepto');
            $table->string('unidad', 20)->nullable();
            $table->decimal('cantidad', 12, 4);
            $table->decimal('precio_unitario', 12, 4);
            $table->decimal('base', 12, 2);
            $table->decimal('tipo_impositivo', 5, 2);
            $table->decimal('cuota_impuesto', 12, 2);
            $table->smallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'compra_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_lineas');
    }
};
