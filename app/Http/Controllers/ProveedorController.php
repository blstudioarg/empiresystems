<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProveedorRequest;
use App\Http\Requests\UpdateProveedorRequest;
use App\Models\Proveedor;
use App\Models\Provincia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProveedorController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            $proveedores = Proveedor::orderBy('nombre')->get();

            return response()->json([
                'data' => $proveedores->map(fn (Proveedor $proveedor) => [
                    'id' => $proveedor->id,
                    'nombre' => $proveedor->razon_social ?: $proveedor->nombre,
                    'razon_social' => $proveedor->razon_social,
                    'nif' => $proveedor->nif,
                    'email' => $proveedor->email,
                    'telefono' => $proveedor->telefono,
                    'ciudad' => $proveedor->ciudad,
                    'direccion' => $proveedor->direccion,
                    'cp' => $proveedor->cp,
                    'provincia' => $proveedor->provincia,
                    'pais' => $proveedor->pais,
                    'notas' => $proveedor->notas,
                    'update_url' => route('proveedores.update', $proveedor),
                    'delete_url' => route('proveedores.destroy', $proveedor),
                ])->values(),
                'totales' => [
                    'total' => $proveedores->count(),
                ],
            ]);
        }

        return view('proveedores.index', [
            'provincias' => Provincia::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(StoreProveedorRequest $request): RedirectResponse|JsonResponse
    {
        Proveedor::create($request->validated());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Proveedor creado correctamente.'], 201);
        }

        return redirect()->route('proveedores.index')->with('success', 'Proveedor creado correctamente.');
    }

    public function update(UpdateProveedorRequest $request, string $proveedor): RedirectResponse|JsonResponse
    {
        // Binding manual (no implícito): ver memoria project_tenant_route_binding.
        $proveedor = Proveedor::findOrFail($proveedor);

        $proveedor->update($request->validated());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Proveedor actualizado correctamente.']);
        }

        return redirect()->route('proveedores.index')->with('success', 'Proveedor actualizado correctamente.');
    }

    public function destroy(Request $request, string $proveedor): RedirectResponse|JsonResponse
    {
        $proveedor = Proveedor::findOrFail($proveedor);

        $proveedor->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Proveedor eliminado correctamente.']);
        }

        return redirect()->route('proveedores.index')->with('success', 'Proveedor eliminado correctamente.');
    }
}
