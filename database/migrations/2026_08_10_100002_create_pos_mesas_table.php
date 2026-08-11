<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 038 — mesas. Cada mesa pertenece a exactamente una zona (FR-010).
 *
 * **El estado libre/ocupada NO se almacena**: se deriva de que exista una `pos_cuentas` en estado
 * `abierta` apuntando a la mesa. Guardarlo sería un dato duplicado que puede desincronizarse y
 * hacer que la sala muestre una mesa libre con consumo pendiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_mesas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('zona_id')->constrained('pos_zonas');
            $table->string('nombre', 40);
            $table->unsignedInteger('orden')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'zona_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_mesas');
    }
};
