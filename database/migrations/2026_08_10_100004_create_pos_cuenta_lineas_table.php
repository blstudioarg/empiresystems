<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 038 — líneas de una cuenta abierta.
 *
 * Concepto, precio y tipo impositivo van **congelados** al añadir: una cuenta abierta durante
 * días sigue siendo cobrable y coherente aunque el catálogo cambie por debajo, e incluso si el
 * artículo se elimina (por eso `articulo_id` es nullable).
 *
 * `cantidad_saldada` existe **desde esta primera migración** aunque el cobro parcial se
 * implemente más tarde (research.md D9): retrofitearla obligaría a migrar datos reales de
 * producción y a revalidar toda la numeración. Ahora es una columna; después, un problema.
 *
 * El suplemento de zona NO se congela aquí: FR-051 exige el valor vigente *al cobrar* y el de la
 * zona *donde se cobra*, que puede no ser la de origen si la cuenta se transfirió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_cuenta_lineas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('cuenta_id')->constrained('pos_cuentas')->cascadeOnDelete();
            $table->foreignId('articulo_id')->nullable()->constrained('articulos');
            $table->string('concepto');
            $table->string('unidad')->nullable();
            $table->decimal('cantidad', 12, 2)->default(1);
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->decimal('suplemento_opciones', 12, 2)->default(0);
            $table->decimal('tipo_impositivo', 5, 2)->default(0);
            $table->decimal('cantidad_saldada', 12, 2)->default(0);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['cuenta_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cuenta_lineas');
    }
};
