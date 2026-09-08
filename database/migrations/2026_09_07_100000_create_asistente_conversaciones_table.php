<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial del asistente IA (feature 045, data-model.md).
 *
 * Hasta ahora la conversación vivía en la sesión y moría con ella. Al persistirla pasa a ser dato
 * personal conservado: el plazo (`asistente.retencion_dias`, 90 días por defecto) y la purga
 * (`asistente:purgar`) son parte obligatoria de esta feature, no un añadido posterior
 * (Principio II).
 *
 * Sin `softDeletes` a propósito: cuando alguien borra una conversación, o cuando la purga la
 * alcanza, los datos se van de verdad. Un borrado lógico dejaría vivo justo lo que se pidió
 * eliminar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistente_conversaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('titulo', 60);

            // Resumen acumulado de la parte ya compactada. `null` = nunca se compactó.
            $table->text('resumen')->nullable();
            $table->unsignedBigInteger('resumido_hasta_mensaje_id')->nullable();

            $table->timestamp('ultima_actividad_en');
            $table->timestamps();

            // El listado del panel es la consulta caliente: por tenant, por persona y por actividad.
            $table->index(['tenant_id', 'user_id', 'ultima_actividad_en'], 'asistente_conv_listado_idx');
            // La purga barre por tenant y actividad, sin filtrar por persona.
            $table->index(['tenant_id', 'ultima_actividad_en'], 'asistente_conv_purga_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistente_conversaciones');
    }
};
