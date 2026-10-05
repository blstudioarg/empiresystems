<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 049 — registro **append-only** de las precuentas emitidas sobre una cuenta abierta.
 *
 * La precuenta no es un documento fiscal (docs/02-facturacion-espana.md §3.2): no tiene número,
 * serie, huella Verifactu ni QR. Cada fila guarda la foto de lo impreso (`lineas`, `total`) para
 * regenerar el documento tal como se entregó, y las dos huellas que permiten derivar si sigue
 * vigente sin mantener ningún flag en `pos_cuentas` (research D4).
 *
 * `cuenta_id` es `restrict`: ninguna cascada puede borrar precuentas (FR-013).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_precuentas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('cuenta_id')->constrained('pos_cuentas')->restrictOnDelete();
            $table->foreignId('mesa_id')->nullable()->constrained('pos_mesas')->nullOnDelete();
            $table->string('mesa_nombre', 100)->nullable();
            $table->string('zona_nombre', 100)->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('emitida_en');
            $table->unsignedInteger('cuenta_version');
            $table->char('huella_consumo', 64);
            $table->char('huella_pendiente', 64);
            $table->boolean('reimpresion')->default(false);
            $table->string('regimen_impositivo', 10);
            $table->decimal('suplemento_zona', 5, 2)->default(0);
            $table->unsignedSmallInteger('comensales')->nullable();
            $table->json('lineas');
            $table->decimal('total', 12, 2);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'cuenta_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_precuentas');
    }
};
