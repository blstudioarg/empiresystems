<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `rectangular` deja de ser una forma de mesa: desde la feature 040 el tamaño se da estirando la
 * mesa por su borde, así que "rectangular" no era más que **una cuadrada más ancha** — mismo
 * reparto de sillas y casi el mismo borde. Mantener las dos opciones solo obligaba al encargado a
 * elegir entre dos cosas que ya no se distinguen.
 *
 * `barra` sí se conserva: su reparto de sillas (solo el lado largo) no se puede derivar del tamaño.
 *
 * Las mesas que eran `rectangular` pasan a `cuadrada` **sin tocar su ocupación en celdas**: siguen
 * midiendo lo mismo en el plano, solo cambia la etiqueta. Por eso el `down()` no es una reversión
 * con pérdida de geometría, pero sí de intención: no queda registro de cuáles se habían marcado
 * como rectangulares, así que al revertir todas se quedan en `cuadrada`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pos_mesas')->where('forma', 'rectangular')->update(['forma' => 'cuadrada']);

        Schema::table('pos_mesas', function (Blueprint $table) {
            $table->enum('forma', ['redonda', 'cuadrada', 'barra'])->default('cuadrada')->change();
        });
    }

    public function down(): void
    {
        Schema::table('pos_mesas', function (Blueprint $table) {
            $table->enum('forma', ['redonda', 'cuadrada', 'rectangular', 'barra'])->default('cuadrada')->change();
        });
    }
};
