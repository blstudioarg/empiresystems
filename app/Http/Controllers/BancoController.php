<?php

namespace App\Http\Controllers;

use App\Models\Banco;
use App\Models\CuentaBancaria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BancoController extends Controller
{
    /**
     * Catálogo de bancos del tenant activo (tenant-scoped vía global scope) para poblar el
     * <x-banco-select> de forma dinámica vía AJAX.
     */
    public function index(): JsonResponse
    {
        $bancos = Banco::orderBy('nombre')->get(['id', 'nombre']);

        return response()->json([
            'data' => $bancos->map(fn (Banco $banco) => [
                'id' => $banco->id,
                'nombre' => $banco->nombre,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->reglas());

        $banco = Banco::create($validated);

        return response()->json([
            'message' => 'Banco creado correctamente.',
            'banco' => $banco->only(['id', 'nombre']),
        ], 201);
    }

    public function update(Request $request, string $banco): JsonResponse
    {
        // Sin binding implícito de ruta (mismo motivo que UnidadController@update):
        // se resuelve manualmente, una vez el TenantScope ya está garantizado.
        $banco = Banco::findOrFail($banco);

        $validated = $request->validate($this->reglas($banco->id));

        $banco->update($validated);

        return response()->json([
            'message' => 'Banco actualizado correctamente.',
            'banco' => $banco->only(['id', 'nombre']),
        ]);
    }

    public function destroy(string $banco): JsonResponse
    {
        $banco = Banco::findOrFail($banco);

        // La FK cuentas_bancarias.banco_id es RESTRICT: no se puede borrar un banco todavía
        // referenciado por alguna cuenta (incluidas las dadas de baja, que conservan la fila).
        $enUso = CuentaBancaria::withTrashed()->where('banco_id', $banco->id)->exists();

        if ($enUso) {
            return response()->json([
                'message' => 'No se puede eliminar el banco porque hay cuentas bancarias que lo utilizan.',
            ], 422);
        }

        $banco->delete();

        return response()->json(['message' => 'Banco eliminado correctamente.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function reglas(?int $ignorarId = null): array
    {
        $unica = Rule::unique('bancos', 'nombre')
            ->where('tenant_id', tenant()->id);

        if ($ignorarId !== null) {
            $unica->ignore($ignorarId);
        }

        return [
            'nombre' => ['required', 'string', 'max:255', $unica],
        ];
    }
}
