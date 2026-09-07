<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turnos de una conversación del asistente (feature 045, data-model.md).
 *
 * Se guardan en el mismo formato que consume la API de Chat Completions (research D3): así
 * reconstruir el contexto es un `map` sobre filas y `AsistenteIa` no se entera de que la
 * conversación cambió de sitio.
 *
 * `contenido` es nullable porque un turno `assistant` que solo trae `tool_calls` no lleva texto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistente_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversacion_id')
                ->constrained('asistente_conversaciones')
                ->cascadeOnDelete();

            $table->string('rol', 16); // user | assistant | tool
            $table->longText('contenido')->nullable();

            // `tool_calls` en un assistant, `tool_call_id` en un tool.
            $table->json('metadatos')->nullable();

            $table->timestamps();

            // Reconstruir el hilo en orden es la consulta caliente.
            $table->index(['conversacion_id', 'id'], 'asistente_msg_hilo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistente_mensajes');
    }
};
