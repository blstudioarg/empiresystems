<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompraEstadoB2bTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function crearCompraFacturae(Tenant $tenant, array $atributos = []): Compra
    {
        $proveedor = Proveedor::factory()->create(['tenant_id' => $tenant->id]);

        return Compra::factory()->facturae()->create(array_merge([
            'tenant_id' => $tenant->id,
            'proveedor_id' => $proveedor->id,
        ], $atributos));
    }

    public function test_cambiar_estado_actualiza_estado_b2b_y_fecha(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $compra = $this->crearCompraFacturae($tenant);

        $response = $this->patch("/compras/{$compra->id}/estado-b2b", ['estado_b2b' => 'aceptada']);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $compra->refresh();
        $this->assertSame('aceptada', $compra->estado_b2b->value);
        $this->assertTrue($compra->estado_b2b_fecha->isToday());
    }

    public function test_valor_invalido_devuelve_422(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $compra = $this->crearCompraFacturae($tenant);

        $response = $this->patch("/compras/{$compra->id}/estado-b2b", ['estado_b2b' => 'no-existe']);

        $response->assertSessionHasErrors('estado_b2b');
    }

    public function test_compra_no_facturae_no_admite_estado_b2b(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $proveedor = Proveedor::factory()->create(['tenant_id' => $tenant->id]);
        $compra = Compra::factory()->create(['tenant_id' => $tenant->id, 'proveedor_id' => $proveedor->id]);

        $response = $this->patch("/compras/{$compra->id}/estado-b2b", ['estado_b2b' => 'aceptada']);

        $response->assertSessionHas('error');
    }

    public function test_listado_filtra_por_estado_b2b(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $recibida = $this->crearCompraFacturae($tenant);
        $aceptada = $this->crearCompraFacturae($tenant, ['estado_b2b' => 'aceptada']);

        $response = $this->getJson('/compras?estado_b2b=aceptada');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($aceptada->id));
        $this->assertFalse($ids->contains($recibida->id));
    }

    public function test_aislamiento_tenant_no_cambia_estado_de_compra_de_otro(): void
    {
        $tenantB = Tenant::factory()->create();
        $compraB = $this->crearCompraFacturae($tenantB);

        $tenantA = Tenant::factory()->create();
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($userA);

        $response = $this->patch("/compras/{$compraB->id}/estado-b2b", ['estado_b2b' => 'aceptada']);

        $response->assertNotFound();
    }
}
