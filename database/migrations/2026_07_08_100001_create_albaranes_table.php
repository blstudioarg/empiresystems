<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('albaranes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('numero', 20);
            $table->foreignId('presupuesto_id')->nullable()->constrained('presupuestos')->nullOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->string('estado', 12)->default('borrador');

            // Snapshot del receptor (mismo patrón que presupuestos.receptor_*), congelado en el documento.
            $table->string('receptor_nombre')->nullable();
            $table->string('receptor_nif', 15)->nullable();
            $table->string('receptor_direccion')->nullable();
            $table->string('receptor_cp', 10)->nullable();
            $table->string('receptor_ciudad')->nullable();
            $table->string('receptor_provincia')->nullable();
            $table->string('receptor_pais', 2)->default('ES');

            $table->date('fecha_entrega')->nullable();

            $table->string('regimen_impositivo', 5);
            $table->boolean('aplica_recargo')->default(false);

            $table->decimal('base_total', 12, 2)->default(0);
            $table->decimal('cuota_impuesto_total', 12, 2)->default(0);
            $table->decimal('cuota_recargo_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->foreignId('convertido_a_factura_id')->nullable()->constrained('facturas')->nullOnDelete();
            $table->text('notas')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'estado']);
            $table->index(['tenant_id', 'cliente_id']);
            $table->index(['tenant_id', 'presupuesto_id']);
            $table->index(['tenant_id', 'convertido_a_factura_id']);
            $table->unique(['tenant_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('albaranes');
    }
};
