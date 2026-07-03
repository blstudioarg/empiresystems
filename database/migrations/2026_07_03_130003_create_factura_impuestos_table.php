<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factura_impuestos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->string('tipo_impuesto');
            $table->decimal('porcentaje', 5, 2);
            $table->decimal('base_imponible', 12, 2);
            $table->decimal('cuota', 12, 2);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('factura_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_impuestos');
    }
};
