<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 038 — cuentas abiertas. El corazón del módulo.
 *
 * **Sin `numero` ni `serie_id`, a propósito.** Es la garantía *estructural* de FR-017: una cuenta
 * abierta no es una factura en borrador y no puede reservar numeración ni de casualidad. El
 * número solo lo asigna `EmisorFacturas` al emitir, así que abrir y anular cuentas nunca deja
 * huecos en la serie (Principio II).
 *
 * **Sin `total`**, tampoco por descuido: el importe pendiente se calcula desde las líneas.
 * Cachearlo abriría la puerta a que la mesa muestre un importe distinto del que se cobra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_cuentas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            // null = venta directa sin mesa: el POS de siempre sigue funcionando igual (FR-021).
            $table->foreignId('mesa_id')->nullable()->constrained('pos_mesas');
            $table->unsignedTinyInteger('comensales')->nullable();
            $table->string('estado')->default('abierta'); // abierta | cerrada | anulada
            $table->foreignId('abierta_por')->nullable()->constrained('users');
            $table->timestamp('abierta_en')->nullable();
            $table->timestamp('cerrada_en')->nullable();
            // Bloqueo optimista (FR-024, research.md D7). Un entero incremental y no `updated_at`:
            // dos escrituras dentro del mismo segundo pueden no distinguirse por timestamp.
            $table->unsignedInteger('version')->default(1);

            // Receptor opcional de la simplificada cualificada, mismo juego de campos que en
            // `facturas`. No introduce ningún dato personal nuevo (RGPD ya cubierto).
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->string('cliente_nombre')->nullable();
            $table->string('cliente_razon_social')->nullable();
            $table->string('cliente_nif', 15)->nullable();
            $table->string('cliente_direccion')->nullable();

            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'estado', 'mesa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cuentas');
    }
};
