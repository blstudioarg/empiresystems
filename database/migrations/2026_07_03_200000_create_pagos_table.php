<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('factura_id')->constrained('facturas');
            $table->date('fecha');
            $table->decimal('importe', 12, 2);
            $table->string('metodo');
            $table->string('referencia', 100)->nullable();
            $table->dateTime('anulado_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'factura_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
