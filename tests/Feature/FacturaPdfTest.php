<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\FacturaLinea;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacturaPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_pdf_responde_200_para_el_tenant_propietario(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Cliente PDF']);
        $factura = Factura::factory()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id]);
        FacturaLinea::factory()->for($factura)->create(['tenant_id' => $tenant->id, 'concepto' => 'Línea de prueba']);

        $this->loginAs($user);

        $response = $this->get("/facturas/{$factura->id}/pdf");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_el_pdf_deniega_acceso_cross_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);
        $facturaB = Factura::factory()->create(['tenant_id' => $tenantB->id, 'cliente_id' => $clienteB->id]);

        $this->loginAs($userA);

        $response = $this->get("/facturas/{$facturaB->id}/pdf");

        $response->assertNotFound();
    }
}
