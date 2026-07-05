<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCuentaBancariaRequest;
use App\Http\Requests\UpdateCuentaBancariaRequest;
use App\Models\CuentaBancaria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CuentaBancariaController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $cuentas = CuentaBancaria::withTrashed()
            ->with('banco')
            ->orderBy('alias')
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $cuentas->map(fn (CuentaBancaria $cuenta) => $this->serializar($cuenta))->values(),
            ]);
        }

        return redirect()->route('configuracion.show');
    }

    public function store(StoreCuentaBancariaRequest $request): RedirectResponse|JsonResponse
    {
        $datos = $request->validated();
        $datos['iban'] = $this->normalizarIban($datos['iban']);
        $datos['activa'] = true;

        $cuenta = CuentaBancaria::create($datos);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cuenta bancaria creada correctamente.', 'id' => $cuenta->id], 201);
        }

        return redirect()->route('configuracion.show')->with('success', 'Cuenta bancaria creada correctamente.');
    }

    public function update(UpdateCuentaBancariaRequest $request, string $id): RedirectResponse|JsonResponse
    {
        // Resolución manual (sin binding implícito) para garantizar el TenantScope activo.
        $cuenta = CuentaBancaria::findOrFail($id);

        $datos = $request->validated();
        $datos['iban'] = $this->normalizarIban($datos['iban']);

        $cuenta->update($datos);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cuenta bancaria actualizada correctamente.']);
        }

        return redirect()->route('configuracion.show')->with('success', 'Cuenta bancaria actualizada correctamente.');
    }

    public function destroy(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $cuenta = CuentaBancaria::findOrFail($id);

        $cuenta->update(['activa' => false]);
        $cuenta->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cuenta bancaria desactivada.']);
        }

        return redirect()->route('configuracion.show')->with('success', 'Cuenta bancaria desactivada.');
    }

    public function restore(Request $request, string $id): RedirectResponse|JsonResponse
    {
        $cuenta = CuentaBancaria::withTrashed()->findOrFail($id);

        $cuenta->restore();
        $cuenta->update(['activa' => true]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Cuenta bancaria reactivada.']);
        }

        return redirect()->route('configuracion.show')->with('success', 'Cuenta bancaria reactivada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializar(CuentaBancaria $cuenta): array
    {
        return [
            'id' => $cuenta->id,
            'banco_id' => $cuenta->banco_id,
            'banco' => $cuenta->banco?->nombre,
            'alias' => $cuenta->alias,
            'iban' => $cuenta->iban,
            'titular' => $cuenta->titular,
            'activa' => $cuenta->activa && $cuenta->deleted_at === null,
            'update_url' => route('cuentas-bancarias.update', $cuenta->id),
            'delete_url' => route('cuentas-bancarias.destroy', $cuenta->id),
            'restore_url' => route('cuentas-bancarias.restore', $cuenta->id),
        ];
    }

    private function normalizarIban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban));
    }
}
