<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 038 — cada emisión realizada sobre una cuenta: una si se cobra entera, varias si se
 * divide. La relación cuenta → factura vive **de este lado**, no como columna nueva en
 * `facturas`: el esquema de facturación está bajo auditoría fiscal y no se toca si se puede
 * evitar (Principio II).
 *
 * Estas dos tablas se crean ahora aunque el cobro parcial se implemente en una tanda posterior
 * (research.md D9), por el mismo motivo que `cantidad_saldada`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_cobros', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('cuenta_id')->constrained('pos_cuentas');
            $table->foreignId('factura_id')->constrained('facturas');
            // % de suplemento de zona vigente EN ESTE cobro, congelado (FR-051).
            $table->decimal('zona_suplemento_aplicado', 5, 2)->default(0);
            $table->foreignId('cobrado_por')->nullable()->constrained('users');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'cuenta_id']);
        });

        Schema::create('pos_cobro_lineas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('cobro_id')->constrained('pos_cobros')->cascadeOnDelete();
            $table->foreignId('cuenta_linea_id')->constrained('pos_cuenta_lineas');
            // Unidades saldadas en ESTE cobro. Invariante crítico (FR-033): por línea, la suma de
            // esta columna debe igualar `pos_cuenta_lineas.cantidad_saldada`. Es lo que hace
            // imposible cobrar dos veces la misma unidad.
            $table->decimal('cantidad', 12, 2);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('cuenta_linea_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cobro_lineas');
        Schema::dropIfExists('pos_cobros');
    }
};
