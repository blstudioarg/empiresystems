<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 050 — memoria de traducción automática de los textos **de la aplicación**.
 *
 * Tabla central, **sin `tenant_id`** a propósito: guarda textos de la app («Cobrar»), no datos de
 * ningún tenant, igual que el catálogo de permisos. Compartirla es lo que hace que cada texto se
 * traduzca una sola vez (SC-009). Justificado en el Complexity Tracking del plan de la 050. Lo que
 * sí es del tenant (idioma y correcciones) lleva `tenant_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traducciones', function (Blueprint $table) {
            $table->id();
            $table->string('idioma', 10);
            $table->char('hash', 64);
            $table->text('texto');
            $table->string('ambito', 40);
            $table->boolean('es_html')->default(false);
            $table->text('traduccion')->nullable();
            $table->string('estado', 12)->default('pendiente');
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->string('ultimo_error', 255)->nullable();
            $table->timestamp('traducida_en')->nullable();
            $table->timestamp('vista_en')->nullable();
            $table->timestamps();

            $table->unique(['idioma', 'hash']);
            $table->index(['idioma', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traducciones');
    }
};
