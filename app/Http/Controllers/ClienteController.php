<?php

namespace App\Http\Controllers;

use App\Enums\TipoCliente;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Models\Cliente;
use App\Models\Provincia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            $clientes = Cliente::orderBy('nombre')->get();

            return response()->json([
                'data' => $clientes->map(fn (Cliente $cliente) => [
                    'id' => $cliente->id,
                    'nombre' => $cliente->razon_social ?: $cliente->nombre,
                    'tipo' => $cliente->tipo->value,
                    'tipo_label' => $cliente->tipo === TipoCliente::Empresa ? 'Empresa' : 'Particular',
                    'nif' => $cliente->nif,
                    'email' => $cliente->email,
                    'telefono' => $cliente->telefono,
                    'ciudad' => $cliente->ciudad,
                    'razon_social' => $cliente->razon_social,
                    'direccion' => $cliente->direccion,
                    'cp' => $cliente->cp,
                    'provincia' => $cliente->provincia,
                    'pais' => $cliente->pais,
                    'recargo' => $cliente->aplica_recargo_equivalencia,
                    'notas' => $cliente->notas,
                    'update_url' => route('clientes.update', $cliente),
                    'delete_url' => route('clientes.destroy', $cliente),
                ])->values(),
                'totales' => [
                    'total' => $clientes->count(),
                    'empresas' => $clientes->where('tipo', TipoCliente::Empresa)->count(),
                    'particulares' => $clientes->where('tipo', TipoCliente::Particular)->count(),
                ],
            ]);
        }

        return view('clientes.index', [
            'provincias' => Provincia::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(StoreClienteRequest $request): RedirectResponse|JsonResponse
    {
        Cliente::create($request->validated());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cliente creado correctamente.'], 201);
        }

        return redirect()->route('clientes.index')->with('success', 'Cliente creado correctamente.');
    }

    public function update(UpdateClienteRequest $request, string $cliente): RedirectResponse|JsonResponse
    {
        // No se usa binding implícito de ruta: en la práctica SubstituteBindings puede ejecutarse
        // antes que el middleware `tenant.context`, dejando el TenantScope todavía sin inicializar
        // y permitiendo resolver clientes de otro tenant. Se resuelve manualmente aquí, donde el
        // scope ya está garantizado (el controller corre al final del pipeline de middleware).
        $cliente = Cliente::findOrFail($cliente);

        $cliente->update($request->validated());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cliente actualizado correctamente.']);
        }

        return redirect()->route('clientes.index')->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Request $request, string $cliente): RedirectResponse|JsonResponse
    {
        $cliente = Cliente::findOrFail($cliente);

        $cliente->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cliente eliminado correctamente.']);
        }

        return redirect()->route('clientes.index')->with('success', 'Cliente eliminado correctamente.');
    }
}
