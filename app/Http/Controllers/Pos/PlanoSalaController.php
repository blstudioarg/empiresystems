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
 * Desde la feature 042 el cuerpo trae también el **lienzo** de la zona (medidas y celdas
 * recortadas), que se persiste en la MISMA transacción y con el MISMO bump de `version` que las
 * mesas: el contorno de la sala y lo que hay dentro son un solo estado, y guardar la mitad dejaría
 * mesas fuera de su propio plano.
 *
 * La zona se resuelve manualmente bajo el tenant activo (nunca binding implícito, ver pitfall de
 * memoria del proyecto sobre `TenantScope`).
 */
class PlanoSalaController extends Controller
{
    public function update(Request $request, string $zona): JsonResponse
    {
        $modelo = PosZona::query()->findOrFail($zona);

        // Las medidas se validan primero por separado: acotan el tamaño máximo que puede tener una
        // mesa, así que tienen que ser un número de confianza ANTES de construir esa regla.
        $lienzo = $request->validate([
            'columnas' => ['required', 'integer', 'min:'.PosPlanoCeldas::MIN, 'max:'.PosPlanoCeldas::MAX],
            'filas' => ['required', 'integer', 'min:'.PosPlanoCeldas::MIN, 'max:'.PosPlanoCeldas::MAX],
        ]);

        $datos = $request->validate([
            'version' => ['required', 'integer'],
            'celdas_inactivas' => ['present', 'array'],
            'celdas_inactivas.*' => ['string', 'regex:/^\d+-\d+$/'],
            // `present` y no `required`: desde la feature 042 el guardado lleva también el lienzo,
            // así que una zona recién creada —sin una sola mesa todavía— tiene que poder guardar la
            // forma de su sala antes de colocar nada dentro.
            'mesas' => ['present', 'array'],
            'mesas.*.id' => ['required', 'integer'],
            'mesas.*.fila' => ['required', 'integer'],
            'mesas.*.columna' => ['required', 'integer'],
            'mesas.*.forma' => ['required', 'string', 'in:redonda,cuadrada,barra'],
            // Acotadas por las medidas DEL PAYLOAD, no por una constante global: en una zona de
            // 5×4 una mesa de 8 celdas de ancho no es "grande", es imposible.
            'mesas.*.ancho_celdas' => ['required', 'integer', 'min:1', 'max:'.$lienzo['columnas']],
            'mesas.*.alto_celdas' => ['required', 'integer', 'min:1', 'max:'.$lienzo['filas']],
        ]) + $lienzo;

        if ((int) $datos['version'] !== (int) $modelo->version) {
            return response()->json([
                'message' => 'El plano se modificó desde otro dispositivo. Recárgalo antes de guardar.',
            ], 409);
        }

        $geometria = [
            'columnas' => (int) $datos['columnas'],
            'filas' => (int) $datos['filas'],
            'celdas_inactivas' => $datos['celdas_inactivas'],
        ];

        $mesasZona = PosPlanoReacomodo::validar($modelo, $geometria, $datos['mesas']);

        $celdasInactivas = PosPlanoReacomodo::normalizarCeldas(
            $geometria['celdas_inactivas'], $geometria['columnas'], $geometria['filas']
        );

        DB::transaction(function () use ($modelo, $datos, $mesasZona, $geometria, $celdasInactivas) {
            foreach ($datos['mesas'] as $item) {
                $mesasZona->get((int) $item['id'])->update([
                    'fila' => $item['fila'],
                    'columna' => $item['columna'],
                    'forma' => $item['forma'],
                    'ancho_celdas' => $item['ancho_celdas'],
                    'alto_celdas' => $item['alto_celdas'],
                ]);
            }

            $modelo->columnas = $geometria['columnas'];
            $modelo->filas = $geometria['filas'];
            $modelo->celdas_inactivas = $celdasInactivas;
            $modelo->version = (int) $modelo->version + 1;
            $modelo->save();
        });

        return response()->json([
            'message' => 'Plano guardado.',
            'version' => $modelo->version,
        ]);
    }
}
