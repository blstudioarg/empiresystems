<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 038 — zonas de sala. El prefijo `pos_` es deliberado (research.md D2): evita la
 * colisión semántica de una tabla `cuentas` en un proyecto de facturación (ya existe
 * `cuentas_bancarias`) y hace evidente qué parte del esquema pertenece al módulo desactivable.
 *
 * Ningún nombre de zona tiene significado para el sistema (FR-009): "Terraza" es un ejemplo, no
 * un concepto. El suplemento es un dato de la zona, no una propiedad de su nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_zonas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('nombre', 60);
            $table->decimal('suplemento_porcentaje', 5, 2)->default(0);
            $table->unsignedInteger('orden')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_zonas');
    }
};
