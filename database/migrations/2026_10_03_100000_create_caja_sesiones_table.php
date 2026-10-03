<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 048 — sesión de caja del POS: el periodo entre una apertura y un cierre.
 *
 * **`abierta_marca` + UNIQUE (tenant_id, abierta_marca)** es la garantía estructural de que un
 * tenant nunca tiene dos cajas abiertas (FR-002, research D2): vale `1` mientras la sesión está
 * abierta y `NULL` al cerrarse, y MySQL/MariaDB/SQLite admiten varios NULL en un índice único. Dos
 * aperturas simultáneas desde dos tablets chocan en el INSERT aunque fallara cualquier otra
 * comprobación.
 *
 * Las cifras del cierre son **columnas congeladas**, no derivadas: el informe Z de un día cerrado
 * no puede cambiar porque después se anule o se emita otro documento (FR-017, research D4).
 *
 * Sin `softDeletes`: una sesión de caja nunca se borra (registro de control contable, D10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caja_sesiones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('estado')->default('abierta'); // abierta | cerrada
            $table->unsignedTinyInteger('abierta_marca')->nullable();

            $table->decimal('fondo_inicial', 12, 2)->default(0);
            $table->json('conteo_apertura')->nullable();
            $table->foreignId('abierta_por')->constrained('users')->restrictOnDelete();
            $table->timestamp('abierta_at');
            $table->foreignId('cerrada_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cerrada_at')->nullable();

            // Cifras congeladas al cerrar (null mientras la sesión está abierta).
            $table->unsignedInteger('num_tickets')->nullable();
            $table->decimal('total_facturado', 12, 2)->nullable();
            $table->decimal('efectivo_ventas', 12, 2)->nullable();
            $table->decimal('entradas', 12, 2)->nullable();
            $table->decimal('salidas', 12, 2)->nullable();
            $table->decimal('efectivo_esperado', 12, 2)->nullable();
            $table->decimal('efectivo_contado', 12, 2)->nullable();
            $table->json('conteo_cierre')->nullable();
            $table->decimal('descuadre', 12, 2)->nullable();
            $table->text('observacion')->nullable();
            $table->json('resumen')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'abierta_marca']);
            $table->index(['tenant_id', 'abierta_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_sesiones');
    }
};
