<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacturaCrudTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(User $user): void
    {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);
    }

    public function test_store_crea_borrador_con_totales_calculados_en_servidor(): void
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => 'iva']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id, 'aplica_recargo_equivalencia' => true]);

        $this->loginAs($user);

        $response = $this->post('/facturas', [
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'irpf_porcentaje' => 15,
            'lineas' => [
                ['concepto' => 'Consultoría', 'cantidad' => 2, 'precio_unitario' => 50, 'tipo_impositivo' => 21],
            ],
            // el cliente intenta colar un total distinto: debe ser ignorado
            'total' => 999999,
        ]);

        $response->assertRedirect(route('facturas.index'));

        $factura = Factura::first();
        $this->assertNotNull($factura);
        $this->assertNull($factura->numero);
        $this->assertEquals('borrador', $factura->estado->value);
        $this->assertEquals(100, $factura->base_total);
        $this->assertEquals(21, $factura->cuota_impuesto_total);
        $this->assertEquals(5.2, $factura->cuota_recargo_total);
        $this->assertEquals(15, $factura->irpf_cuota);
        $this->assertEquals(111.2, $factura->total);
        $this->assertEquals('iva', $factura->regimen_impositivo->value);
        $this->assertTrue($factura->aplica_recargo);
    }

    public function test_store_falla_sin_cliente(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $response = $this->post('/facturas', [
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'lineas' => [
                ['concepto' => 'Consultoría', 'cantidad' => 2, 'precio_unitario' => 50, 'tipo_impositivo' => 21],
            ],
        ]);

        $response->assertSessionHasErrors('cliente_id');
        $this->assertDatabaseCount('facturas', 0);
    }

    public function test_store_falla_sin_lineas(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $this->loginAs($user);

        $response = $this->post('/facturas', [
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'lineas' => [],
        ]);

        $response->assertSessionHasErrors('lineas');
        $this->assertDatabaseCount('facturas', 0);
    }

    public function test_store_falla_con_tipo_impositivo_fuera_de_regimen(): void
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => 'iva']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $this->loginAs($user);

        $response = $this->post('/facturas', [
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'lineas' => [
                ['concepto' => 'Consultoría', 'cantidad' => 1, 'precio_unitario' => 50, 'tipo_impositivo' => 7],
            ],
        ]);

        $response->assertSessionHasErrors('lineas.0.tipo_impositivo');
        $this->assertDatabaseCount('facturas', 0);
    }

    public function test_update_recalcula_totales(): void
    {
        $tenant = Tenant::factory()->create(['regimen_impositivo' => 'iva']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $this->loginAs($user);

        $this->post('/facturas', [
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'lineas' => [
                ['concepto' => 'Consultoría', 'cantidad' => 1, 'precio_unitario' => 100, 'tipo_impositivo' => 21],
            ],
        ]);

        $factura = Factura::first();

        $response = $this->put("/facturas/{$factura->id}", [
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'lineas' => [
                ['concepto' => 'Consultoría', 'cantidad' => 2, 'precio_unitario' => 100, 'tipo_impositivo' => 21],
            ],
        ]);

        $response->assertRedirect(route('facturas.index'));

        $factura->refresh();
        $this->assertEquals(200, $factura->base_total);
        $this->assertEquals(42, $factura->cuota_impuesto_total);
        $this->assertCount(1, $factura->lineas);
    }

    public function test_destroy_solo_borra_borrador(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $factura = Factura::factory()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'estado' => 'emitida',
        ]);

        $this->loginAs($user);

        $response = $this->delete("/facturas/{$factura->id}");

        $response->assertForbidden();
        $this->assertNotSoftDeleted($factura);
    }

    public function test_edit_precarga_los_datos_de_la_factura(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $factura = Factura::factory()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id]);

        $this->loginAs($user);

        $response = $this->get("/facturas/{$factura->id}/editar");

        $response->assertOk();
        $response->assertViewHas('factura', fn ($f) => $f->id === $factura->id);
    }
}
