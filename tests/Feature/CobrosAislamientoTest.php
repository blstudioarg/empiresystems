<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Serie;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * Aislamiento multi-tenant del módulo de Cobros (feature 043, Principio I, SC-006). Se escribe
 * ANTES de que CobroController/ConsultaCobros existan: debe fallar primero (Principio IV).
 */
class CobrosAislamientoTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    private function crearEscenario(): array
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $clienteA = Cliente::factory()->for($tenantA, 'tenant')->create(['nombre' => 'Cliente Tenant A']);
        $serieA = Serie::factory()->for($tenantA, 'tenant')->create();
        $facturaA = Factura::factory()->for($tenantA, 'tenant')->emitida()->create([
            'cliente_id' => $clienteA->id,
            'serie_id' => $serieA->id,
            'total' => 500,
        ]);
        Pago::factory()->for($tenantA, 'tenant')->create([
            'factura_id' => $facturaA->id,
            'importe' => 100,
        ]);

        $clienteB = Cliente::factory()->for($tenantB, 'tenant')->create(['nombre' => 'Cliente Tenant B']);
        $serieB = Serie::factory()->for($tenantB, 'tenant')->create();
        $facturaB = Factura::factory()->for($tenantB, 'tenant')->emitida()->create([
            'cliente_id' => $clienteB->id,
            'serie_id' => $serieB->id,
            'total' => 9000,
        ]);
        Pago::factory()->for($tenantB, 'tenant')->create([
            'factura_id' => $facturaB->id,
            'importe' => 4000,
        ]);

        $this->sembrarPermisos();
        $rolA = $this->crearRol($tenantA, 'Cobros A', ['ver-cobros']);
        $usuarioA = $this->usuarioConRol($tenantA, $rolA);

        return compact('tenantA', 'tenantB', 'facturaA', 'facturaB', 'usuarioA');
    }

    public function test_el_listado_de_un_tenant_no_incluye_facturas_de_otro(): void
    {
        $datos = $this->crearEscenario();

        $this->loginAs($datos['usuarioA']);

        $response = $this->getJson('/cobros?draw=1&start=0&length=50');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($datos['facturaA']->id, $ids);
        $this->assertNotContains($datos['facturaB']->id, $ids);
    }

    public function test_el_resumen_de_un_tenant_no_suma_importes_de_otro(): void
    {
        $datos = $this->crearEscenario();

        $this->loginAs($datos['usuarioA']);

        $response = $this->getJson('/cobros/resumen');

        $response->assertOk();

        // Tenant A: total 500, cobrado 100 => pendiente 400. Si se filtrara el tenant B se vería
        // un pendiente mucho mayor (9000 - 4000 = 5000 adicionales).
        $this->assertEquals('400.00', $response->json('pendiente_total'));
        $this->assertEquals(1, $response->json('facturas_pendientes'));
    }
}
