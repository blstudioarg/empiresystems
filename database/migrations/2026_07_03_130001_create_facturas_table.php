<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('serie_id')->constrained('series');
            $table->unsignedInteger('numero')->nullable();
            $table->string('numero_completo')->nullable();
            $table->string('tipo');
            $table->string('estado');
            $table->foreignId('cliente_id')->constrained('clientes');

            // Snapshot de los datos del receptor en el momento de la factura (art. 4 factura
            // completa: NIF, nombre/razón social y domicilio). Se precargan desde el cliente al
            // seleccionarlo pero son propios de la factura y quedan editables/congelados aunque
            // el cliente cambie después.
            $table->string('cliente_nombre')->nullable();
            $table->string('cliente_razon_social')->nullable();
            $table->string('cliente_nif', 15)->nullable();
            $table->string('cliente_direccion')->nullable();
            $table->string('cliente_cp', 10)->nullable();
            $table->string('cliente_ciudad')->nullable();
            $table->string('cliente_provincia')->nullable();
            $table->string('cliente_pais', 2)->default('ES');

            $table->date('fecha_expedicion');
            $table->date('fecha_operacion')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->string('forma_pago');
            $table->char('moneda', 3)->default('EUR');
            $table->string('regimen_impositivo');
            $table->boolean('aplica_recargo')->default(false);
            $table->decimal('base_total', 12, 2)->default(0);
            $table->decimal('cuota_impuesto_total', 12, 2)->default(0);
            $table->decimal('cuota_recargo_total', 12, 2)->default(0);
            $table->decimal('irpf_porcentaje', 5, 2)->nullable();
            $table->decimal('irpf_cuota', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notas')->nullable();

            // Verifactu (nullable, sin usar en esta feature)
            $table->string('huella', 64)->nullable();
            $table->string('huella_anterior', 64)->nullable();
            $table->text('qr_contenido')->nullable();
            $table->string('verifactu_estado')->default('pendiente');
            $table->longText('registro_xml')->nullable();
            $table->dateTime('registrada_at')->nullable();

            // Ciclo B2B (nullable, sin usar en esta feature)
            $table->string('estado_b2b')->nullable();
            $table->dateTime('estado_b2b_fecha')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'serie_id', 'numero']);
            $table->index(['tenant_id', 'cliente_id']);
            $table->index(['tenant_id', 'estado']);
            $table->index(['tenant_id', 'fecha_expedicion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
