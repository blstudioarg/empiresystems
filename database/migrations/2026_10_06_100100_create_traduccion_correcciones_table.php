<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 050 — corrección manual de un tenant sobre la traducción de un texto (FR-016/017/018).
 *
 * Prevalece sobre `traducciones` solo para su tenant. Va en tabla aparte para que la traducción
 * automática nunca pueda pisarla (FR-017). `texto` es copia del español: la fila sigue siendo
 * legible aunque ese texto cambie en el código y deje de aplicarse (US4-3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traduccion_correcciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('idioma', 10);
            $table->char('hash', 64);
            $table->text('texto');
            $table->text('traduccion');
            $table->foreignId('corregida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'idioma', 'hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traduccion_correcciones');
    }
};
