<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Desglose interno de cómo se cobró un ticket (factura simplificada) en caja: uno o varios
     * métodos de pago con su importe. Es puramente informativo/interno (no participa del sistema
     * de cobros `pagos` ni del dashboard) y no se refleja en el PDF del ticket.
     */
    public function up(): void
    {
        Schema::create('ticket_pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->string('metodo');
            $table->decimal('importe', 12, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'factura_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_pagos');
    }
};
