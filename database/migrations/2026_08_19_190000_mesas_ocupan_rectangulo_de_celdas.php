<?php

use App\Support\PosPlanoConversion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 040 — una mesa pasa a ocupar un rectángulo de celdas de la rejilla del plano, en vez de
 * una sola celda con un tamaño decorativo en píxeles.
 *
 * Añade `ancho_celdas`/`alto_celdas` a `pos_mesas`, convierte los planos existentes con
 * {@see PosPlanoConversion} (deriva de `forma` y corrige lo que se saldría de la rejilla o pisaría a
 * otra mesa, D8 de research.md) y elimina `tamano`.
 *
 * **El `down()` pierde información**: el modelo antiguo no puede representar un 3×2, así que revertir
 * devuelve todas las mesas a `tamano = 'mediana'` y descarta la ocupación en celdas. Por la regla del
 * proyecto sobre datos de demo, esta migración se aplica con `php artisan migrate`, nunca con
 * `migrate:fresh`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_mesas', function (Blueprint $table) {
            $table->unsignedTinyInteger('ancho_celdas')->default(1)->after('columna');
            $table->unsignedTinyInteger('alto_celdas')->default(1)->after('ancho_celdas');
        });

        $this->convertirPlanosExistentes();

        Schema::table('pos_mesas', function (Blueprint $table) {
            $table->dropColumn('tamano');
        });
    }

    public function down(): void
    {
        Schema::table('pos_mesas', function (Blueprint $table) {
            $table->enum('tamano', ['pequena', 'mediana', 'grande'])->default('mediana')->after('forma');
        });

        Schema::table('pos_mesas', function (Blueprint $table) {
            $table->dropColumn(['ancho_celdas', 'alto_celdas']);
        });
    }

    /**
     * Zona por zona (el invariante de no solapamiento es local a una zona), sin pasar por Eloquent:
     * una migración no debe depender del `TenantScope` ni del `$fillable` vigentes.
     */
    private function convertirPlanosExistentes(): void
    {
        $mesas = DB::table('pos_mesas')
            ->whereNull('deleted_at')
            ->get(['id', 'zona_id', 'fila', 'columna', 'forma']);

        foreach ($mesas->groupBy('zona_id') as $mesasZona) {
            $entrada = $mesasZona->map(fn ($m) => [
                'id' => (int) $m->id,
                'fila' => $m->fila === null ? null : (int) $m->fila,
                'columna' => $m->columna === null ? null : (int) $m->columna,
                'forma' => $m->forma,
            ])->all();

            foreach (PosPlanoConversion::convertirZona($entrada) as $id => $ocupacion) {
                DB::table('pos_mesas')->where('id', $id)->update($ocupacion);
            }
        }
    }
};
