<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuestos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('numero', 20);
            $table->foreignId('oportunidad_id')->nullable()->constrained('oportunidades')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('estado', 12)->default('borrador');

            // Snapshot del receptor (mismo patrón que facturas.cliente_*), congelado en el documento.
            $table->string('receptor_nombre')->nullable();
            $table->string('receptor_nif', 15)->nullable();
            $table->string('receptor_direccion')->nullable();
            $table->string('receptor_cp', 10)->nullable();
            $table->string('receptor_ciudad')->nullable();
            $table->string('receptor_provincia')->nullable();
            $table->string('receptor_pais', 2)->default('ES');

            $table->date('fecha_emision');
            $table->date('fecha_validez')->nullable();
            $table->dateTime('fecha_envio')->nullable();

            $table->string('regimen_impositivo', 5);
            $table->boolean('aplica_recargo')->default(false);

            $table->decimal('base_total', 12, 2)->default(0);
            $table->decimal('cuota_impuesto_total', 12, 2)->default(0);
            $table->decimal('cuota_recargo_total', 12, 2)->default(0);
            $table->decimal('irpf_porcentaje', 5, 2)->nullable();
            $table->decimal('irpf_cuota', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->foreignId('convertido_a_factura_id')->nullable()->constrained('facturas')->nullOnDelete();
            $table->text('notas')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'estado']);
            $table->index(['tenant_id', 'oportunidad_id']);
            $table->index(['tenant_id', 'cliente_id']);
            $table->unique(['tenant_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuestos');
    }
};
