<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 048 — entradas y salidas manuales de efectivo dentro de una sesión de caja.
 *
 * Ledger de **solo alta**, mismo criterio que `movimientos_stock` y `fichajes`: un movimiento
 * equivocado se corrige con otro del tipo contrario, nunca editando ni borrando (FR-008).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caja_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('caja_sesion_id')->constrained('caja_sesiones')->restrictOnDelete();
            $table->string('tipo'); // entrada | salida
            $table->decimal('importe', 12, 2);
            $table->string('motivo', 160);
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'caja_sesion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_movimientos');
    }
};
