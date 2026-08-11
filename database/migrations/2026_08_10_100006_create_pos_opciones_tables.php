<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 038 — recetario de opciones de artículo (modificadores).
 *
 * Dos pivots y no uno, a propósito: el **grupo** se asigna al artículo, pero el **precio** se
 * ajusta por opción y por artículo. Eso es lo que permite que "Extra queso" cueste 1,50 € en el
 * solomillo y 0,50 € en el bocadillo sin tocar el `precio_defecto` de la opción ni el precio en
 * los demás artículos (FR-039). Una fila ausente en `pos_articulo_opcion` significa "esta opción
 * no aplica a este artículo".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_opcion_grupos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('nombre', 60);
            $table->unsignedTinyInteger('min_selecciones')->default(0);
            $table->unsignedTinyInteger('max_selecciones')->nullable(); // null = sin límite
            $table->boolean('obligatorio')->default(false);
            $table->unsignedInteger('orden')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'nombre']);
        });

        Schema::create('pos_opciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('grupo_id')->constrained('pos_opcion_grupos');
            $table->string('nombre', 60);
            $table->decimal('precio_defecto', 12, 2)->default(0);
            // Opción que consume otro artículo del catálogo: mueve stock pero NO genera línea
            // propia en el documento (FR-052, research.md D5).
            $table->foreignId('articulo_vinculado_id')->nullable()->constrained('articulos');
            $table->unsignedInteger('orden')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'grupo_id', 'nombre']);
        });

        Schema::create('pos_articulo_grupo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('articulo_id')->constrained('articulos')->cascadeOnDelete();
            $table->foreignId('grupo_id')->constrained('pos_opcion_grupos')->cascadeOnDelete();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['articulo_id', 'grupo_id']);
        });

        Schema::create('pos_articulo_opcion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('articulo_id')->constrained('articulos')->cascadeOnDelete();
            $table->foreignId('opcion_id')->constrained('pos_opciones')->cascadeOnDelete();
            $table->decimal('precio', 12, 2)->default(0);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
            // Índice por artículo: el catálogo del POS necesita saber sin coste qué artículos
            // abren modal de opciones y cuáles se añaden en un toque (SC-004).
            $table->index('articulo_id');
            $table->unique(['articulo_id', 'opcion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_articulo_opcion');
        Schema::dropIfExists('pos_articulo_grupo');
        Schema::dropIfExists('pos_opciones');
        Schema::dropIfExists('pos_opcion_grupos');
    }
};
