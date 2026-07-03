<?php

namespace App\Http\Controllers;

use App\Models\Localidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocalidadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provincia_id' => ['required', 'string', 'exists:provincias,id'],
        ]);

        $localidades = Localidad::query()
            ->where('provincia_id', $validated['provincia_id'])
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return response()->json($localidades);
    }
}
