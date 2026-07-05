<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_stock', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('articulo_id')->constrained('articulos')->restrictOnDelete();
            $table->string('tipo');
            $table->decimal('cantidad', 12, 4);
            $table->decimal('stock_resultante', 12, 4);
            $table->string('origen');
            $table->foreignId('factura_id')->nullable()->constrained('facturas')->nullOnDelete();
            $table->foreignId('compra_id')->nullable()->constrained('compras')->nullOnDelete();
            $table->string('motivo')->nullable();
            $table->dateTime('ocurrido_at');
            $table->timestamps();

            $table->index(['tenant_id', 'articulo_id', 'ocurrido_at'], 'movimientos_stock_tenant_articulo_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_stock');
    }
};
