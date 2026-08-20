<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosZona;
use App\Support\PosPlanoCeldas;
use App\Support\PosPlanoReacomodo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Guardado del plano de una zona (feature 039): batch por zona con bloqueo optimista, mismo patrón
 * `version` que {@see \App\Http\Controllers\Pos\CuentaController}.
 *
 * La zona se resuelve manualmente bajo el tenant activo (nunca binding implícito, ver pitfall de
 * memoria del proyecto sobre `TenantScope`).
 */
class PlanoSalaController extends Controller
{
    public function update(Request $request, string $zona): JsonResponse
    {
        $modelo = PosZona::query()->findOrFail($zona);

        $datos = $request->validate([
            'version' => ['required', 'integer'],
            'mesas' => ['required', 'array'],
            'mesas.*.id' => ['required', 'integer'],
            'mesas.*.fila' => ['required', 'integer'],
            'mesas.*.columna' => ['required', 'integer'],
            'mesas.*.forma' => ['required', 'string', 'in:redonda,cuadrada,barra'],
            'mesas.*.ancho_celdas' => ['required', 'integer', 'min:1', 'max:'.PosPlanoCeldas::COLUMNAS],
            'mesas.*.alto_celdas' => ['required', 'integer', 'min:1', 'max:'.PosPlanoCeldas::FILAS],
        ]);

        if ((int) $datos['version'] !== (int) $modelo->version) {
            return response()->json([
                'message' => 'El plano se modificó desde otro dispositivo. Recárgalo antes de guardar.',
            ], 409);
        }

        $mesasZona = PosPlanoReacomodo::validar($modelo, $datos['mesas']);

        DB::transaction(function () use ($modelo, $datos, $mesasZona) {
            foreach ($datos['mesas'] as $item) {
                $mesasZona->get((int) $item['id'])->update([
                    'fila' => $item['fila'],
                    'columna' => $item['columna'],
                    'forma' => $item['forma'],
                    'ancho_celdas' => $item['ancho_celdas'],
                    'alto_celdas' => $item['alto_celdas'],
                ]);
            }

            $modelo->version = (int) $modelo->version + 1;
            $modelo->save();
        });

        return response()->json([
            'message' => 'Plano guardado.',
            'version' => $modelo->version,
        ]);
    }
}
