<?php

namespace Tests\Feature\Pos;

use App\Models\PosCuenta;
use App\Models\PosCuentaLinea;
use App\Models\PosPrecuenta;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * Principio I: ni se emite una precuenta sobre la cuenta de otro tenant, ni se ve su PDF, ni sus
 * filas aparecen al consultar desde otro tenant — **tampoco por id directo**.
 */
class AislamientoPrecuentaTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    /** @return array{0: Tenant, 1: User} */
    private function tenantConSala(string $nombreRol): array
    {
        $tenant = Tenant::factory()->create();
        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true]);
        $rol = $this->crearRol($tenant, $nombreRol, ['ver-pos-sala', 'ver-pos-crear']);

        return [$tenant, $this->usuarioConRol($tenant, $rol)];
    }

    public function test_no_se_emite_ni_se_ve_una_precuenta_de_otro_tenant(): void
    {
        $this->sembrarPermisos();
        [$tenantA, $userA] = $this->tenantConSala('Sala A');
        [$tenantB] = $this->tenantConSala('Sala B');

        $cuentaB = PosCuenta::factory()->create(['tenant_id' => $tenantB->id]);
        PosCuentaLinea::factory()->create(['tenant_id' => $tenantB->id, 'cuenta_id' => $cuentaB->id]);
        $precuentaB = PosPrecuenta::factory()->create(['tenant_id' => $tenantB->id, 'cuenta_id' => $cuentaB->id]);

        $cuentaA = PosCuenta::factory()->create(['tenant_id' => $tenantA->id]);
        PosPrecuenta::factory()->create(['tenant_id' => $tenantA->id, 'cuenta_id' => $cuentaA->id]);

        $this->loginAs($userA);

        $this->postJson("/pos/cuentas/{$cuentaB->id}/precuentas", ['version' => 1])->assertNotFound();
        $this->get("/pos/precuentas/{$precuentaB->id}/pdf")->assertNotFound();

        $this->assertSame(1, PosPrecuenta::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->count());
        $this->assertSame([$tenantA->id], PosPrecuenta::all()->pluck('tenant_id')->unique()->values()->all());
    }
}
