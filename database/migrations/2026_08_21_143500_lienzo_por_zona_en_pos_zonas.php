<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 042 — el lienzo del plano deja de ser una constante global y pasa a ser un dato de cada
 * zona: sus medidas (`columnas` × `filas`) y las celdas que no son sala (`celdas_inactivas`).
 *
 * **No hay backfill a propósito** (D10 de research.md): los defaults 8×6 y `[]` reproducen
 * exactamente la rejilla fija que la feature 039 daba por supuesta, así que toda zona existente
 * conserva su plano tal cual, sin una sola escritura.
 *
 * `celdas_inactivas` va `nullable` y no `default('[]')` porque MySQL no admite DEFAULT en una
 * columna JSON. El valor ausente se lee como `[]` (ver {@see \App\Models\PosZona::celdasInactivas()}),
 * que es exactamente el default del contrato.
 *
 * El `down()` devuelve la rejilla fija: pierde el diseño de sala (medidas propias y recortes),
 * pero **ninguna mesa** — `pos_mesas` no se toca aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_zonas', function (Blueprint $table) {
            $table->unsignedTinyInteger('columnas')->default(8)->after('suplemento_porcentaje');
            $table->unsignedTinyInteger('filas')->default(6)->after('columnas');
            $table->json('celdas_inactivas')->nullable()->after('filas');
        });
    }

    public function down(): void
    {
        Schema::table('pos_zonas', function (Blueprint $table) {
            $table->dropColumn(['columnas', 'filas', 'celdas_inactivas']);
        });
    }
};
