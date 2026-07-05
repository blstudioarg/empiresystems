<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->string('numero_documento')->nullable();
            $table->date('fecha');
            $table->string('estado')->default('borrador');
            $table->decimal('base_total', 12, 2)->default(0);
            $table->decimal('cuota_impuesto_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notas')->nullable();
            $table->dateTime('confirmada_at')->nullable();
            $table->dateTime('anulada_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'proveedor_id']);
            $table->index(['tenant_id', 'fecha']);
            $table->index(['tenant_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
