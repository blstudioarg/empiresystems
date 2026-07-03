<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factura_eventos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('factura_id')->nullable()->constrained('facturas');
            $table->string('tipo_evento', 30);
            $table->json('detalle')->nullable();
            $table->string('huella', 64)->nullable();
            $table->dateTime('ocurrido_at');
            $table->timestamps();

            $table->index(['tenant_id', 'factura_id']);
            $table->index(['tenant_id', 'tipo_evento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_eventos');
    }
};
