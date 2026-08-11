<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 038 — opciones concretas elegidas para una línea de cuenta.
 *
 * `nombre`, `precio` y `articulo_vinculado_id` van **congelados** al añadir. Es lo que permite
 * que una cuenta abierta durante días siga siendo cobrable y coherente aunque la opción se
 * renombre, cambie de precio o se elimine del recetario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_cuenta_linea_opciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('cuenta_linea_id')->constrained('pos_cuenta_lineas')->cascadeOnDelete();
            $table->foreignId('opcion_id')->nullable()->constrained('pos_opciones');
            $table->string('nombre');
            $table->decimal('precio', 12, 2)->default(0);
            $table->foreignId('articulo_vinculado_id')->nullable()->constrained('articulos');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('cuenta_linea_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cuenta_linea_opciones');
    }
};
