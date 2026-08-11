<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 039 — plano de sala arrastrable. `pos_mesas` gana posición (`fila`/`columna`, rejilla
 * 8×6 por zona, D2 de research.md) y mobiliario (`forma`/`tamano`); `pos_zonas` gana `version` para
 * el bloqueo optimista del guardado del plano (D5), mismo patrón que `PosCuenta::version`.
 *
 * El backfill (D7) coloca cada mesa existente en la primera celda libre de su zona, recorriendo en
 * el orden actual (`orden`, `nombre`), para que ninguna mesa quede sin posición válida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_zonas', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('orden');
        });

        Schema::table('pos_mesas', function (Blueprint $table) {
            $table->unsignedTinyInteger('fila')->nullable()->after('orden');
            $table->unsignedTinyInteger('columna')->nullable()->after('fila');
            $table->enum('forma', ['redonda', 'cuadrada', 'rectangular', 'barra'])->default('cuadrada')->after('columna');
            $table->enum('tamano', ['pequena', 'mediana', 'grande'])->default('mediana')->after('forma');

            $table->unique(['tenant_id', 'zona_id', 'fila', 'columna'], 'pos_mesas_tenant_zona_fila_columna_unique');
        });

        $this->backfillPosiciones();
    }

    public function down(): void
    {
        Schema::table('pos_mesas', function (Blueprint $table) {
            $table->dropUnique('pos_mesas_tenant_zona_fila_columna_unique');
            $table->dropColumn(['fila', 'columna', 'forma', 'tamano']);
        });

        Schema::table('pos_zonas', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }

    /**
     * Recorre cada zona (de cada tenant) y asigna a sus mesas sin posición la primera celda libre
     * de la rejilla 8×6 (columnas 0-7, filas 0-5), en el orden `orden`, `nombre`.
     */
    private function backfillPosiciones(): void
    {
        $zonaIds = DB::table('pos_zonas')->pluck('id');

        foreach ($zonaIds as $zonaId) {
            $mesas = DB::table('pos_mesas')
                ->where('zona_id', $zonaId)
                ->whereNull('deleted_at')
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id']);

            $celda = 0;
            $totalCeldas = 8 * 6;

            foreach ($mesas as $mesa) {
                if ($celda >= $totalCeldas) {
                    break;
                }

                DB::table('pos_mesas')->where('id', $mesa->id)->update([
                    'fila' => intdiv($celda, 8),
                    'columna' => $celda % 8,
                ]);

                $celda++;
            }
        }
    }
};
