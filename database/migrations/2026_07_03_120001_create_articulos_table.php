<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articulos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('tipo');
            $table->string('sku', 50)->nullable();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('unidad', 20)->nullable();
            $table->decimal('precio', 12, 4);
            $table->decimal('tipo_impositivo', 5, 2);
            $table->boolean('gestion_stock')->default(false);
            $table->decimal('stock_actual', 12, 4)->nullable();
            $table->decimal('stock_minimo', 12, 4)->nullable();
            $table->decimal('irpf_defecto', 5, 2)->nullable();
            $table->boolean('aplica_recargo_equivalencia')->default(false);
            $table->boolean('activo')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'tipo']);
            $table->index(['tenant_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articulos');
    }
};
