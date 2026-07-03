<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacturaEmisionTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function facturaBorradorValida(Tenant $tenant, Serie $serie): Factura
    {
        $cliente = Cliente::factory()->create([
            'tenant_id' => $tenant->id,
            'nif' => '12345678Z',
            'nombre' => 'Cliente de prueba',
            'direccion' => 'Calle Falsa 123',
        ]);

        return Factura::factory()->create([
            'tenant_id' => $tenant->id,
            'serie_id' => $serie->id,
            'cliente_id' => $cliente->id,
            'cliente_nombre' => $cliente->nombre,
            'cliente_nif' => $cliente->nif,
            'cliente_direccion' => $cliente->direccion,
            'estado' => 'borrador',
            'numero' => null,
            'numero_completo' => null,
            'base_total' => 100,
            'total' => 121,
        ]);
    }

    public function test_emitir_en_un_tenant_no_cambia_la_numeracion_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $serieA = Serie::factory()->create(['tenant_id' => $tenantA->id]);
        $serieB = Serie::factory()->create(['tenant_id' => $tenantB->id]);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);

        $facturaA = $this->facturaBorradorValida($tenantA, $serieA);
        $facturaB = $this->facturaBorradorValida($tenantB, $serieB);

        $this->loginAs($userA);
        $this->post("/facturas/{$facturaA->id}/emitir");

        $this->assertEquals(1, $facturaA->refresh()->numero);
        $this->assertNull($facturaB->refresh()->numero);
        $this->assertEquals(1, $serieB->fresh()->proximo_numero);
    }

    public function test_no_se_puede_emitir_una_factura_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $serieB = Serie::factory()->create(['tenant_id' => $tenantB->id]);
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $facturaB = $this->facturaBorradorValida($tenantB, $serieB);

        $this->loginAs($userA);

        $response = $this->post("/facturas/{$facturaB->id}/emitir");

        $response->assertNotFound();
        $this->assertNull($facturaB->refresh()->numero);
    }
}
