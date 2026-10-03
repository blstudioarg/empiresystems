<?php

namespace Tests\Concerns;

use App\Models\CajaSesion;
use App\Models\Tenant;
use App\Models\User;

/**
 * Desde la feature 048, cobrar en el POS exige una caja abierta (FR-020). Los tests que emiten
 * tickets usan este trait para que esa regla nueva no les cambie nada: **cada tenant creado durante
 * el test nace con su caja abierta** (fondo 0), así que no hay que tocar sus helpers ni sus
 * aserciones (research D3).
 *
 * Los tests de la propia caja que necesitan partir de "caja cerrada" simplemente no usan el trait
 * y abren con {@see abrirCaja()} cuando les toca.
 */
trait ConCajaAbierta
{
    /** Hook de Laravel: `setUp{NombreDelTrait}` se invoca solo tras arrancar la aplicación. */
    protected function setUpConCajaAbierta(): void
    {
        Tenant::created(function (Tenant $tenant) {
            $this->abrirCaja($tenant);
        });
    }

    protected function abrirCaja(Tenant $tenant, float|string $fondo = 0, ?User $por = null): CajaSesion
    {
        $por ??= User::factory()->create(['tenant_id' => $tenant->id]);

        return CajaSesion::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'estado' => CajaSesion::ESTADO_ABIERTA,
            'abierta_marca' => 1,
            'fondo_inicial' => $fondo,
            'abierta_por' => $por->id,
            'abierta_at' => now(),
        ]);
    }
}
