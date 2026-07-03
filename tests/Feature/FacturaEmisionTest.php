<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\FacturaEvento;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacturaEmisionTest extends TestCase
{
    use RefreshDatabase;

    private function facturaBorradorValida(Tenant $tenant, array $overrides = []): Factura
    {
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id, 'codigo' => 'F', 'formato' => '{serie}-{anio}-{numero:0000}']);
        $cliente = Cliente::factory()->create([
            'tenant_id' => $tenant->id,
            'nif' => '12345678Z',
            'nombre' => 'Cliente de prueba',
            'direccion' => 'Calle Falsa 123',
        ]);

        return Factura::factory()->create(array_merge([
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
        ], $overrides));
    }

    public function test_emitir_asigna_numero_estado_y_congela_fecha_sin_alterar_importes(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $factura = $this->facturaBorradorValida($tenant);

        $this->loginAs($user);

        $response = $this->post("/facturas/{$factura->id}/emitir");

        $response->assertRedirect(route('facturas.index'));

        $factura->refresh();
        $this->assertEquals('emitida', $factura->estado->value);
        $this->assertEquals(1, $factura->numero);
        $this->assertEquals("F-{$factura->fecha_expedicion->year}-0001", $factura->numero_completo);
        $this->assertEquals(now()->toDateString(), $factura->fecha_expedicion->toDateString());
        $this->assertEquals(100, (float) $factura->base_total);
        $this->assertEquals(121, (float) $factura->total);
    }

    public function test_emitir_rechaza_sin_lineas_con_importe(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $factura = $this->facturaBorradorValida($tenant, ['base_total' => 0, 'total' => 0]);

        $this->loginAs($user);

        $response = $this->postJson("/facturas/{$factura->id}/emitir");

        $response->assertStatus(422);
        $factura->refresh();
        $this->assertEquals('borrador', $factura->estado->value);
        $this->assertNull($factura->numero);
    }

    public function test_emitir_rechaza_datos_fiscales_del_receptor_incompletos(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $factura = $this->facturaBorradorValida($tenant, ['cliente_nif' => null]);

        $this->loginAs($user);

        $response = $this->postJson("/facturas/{$factura->id}/emitir");

        $response->assertStatus(422);
        $factura->refresh();
        $this->assertEquals('borrador', $factura->estado->value);
        $this->assertNull($factura->numero);
    }

    public function test_listado_json_expone_flags_de_acciones_segun_estado(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $borrador = $this->facturaBorradorValida($tenant);
        $emitida = $this->facturaBorradorValida($tenant);

        $this->loginAs($user);
        $this->post("/facturas/{$emitida->id}/emitir");

        $response = $this->getJson('/facturas');
        $response->assertOk();

        $filas = collect($response->json('data'))->keyBy('id');

        $filaBorrador = $filas[$borrador->id];
        $this->assertTrue($filaBorrador['es_borrador']);
        $this->assertEquals('Borrador', $filaBorrador['identificador']);
        $this->assertNotNull($filaBorrador['emitir_url']);
        $this->assertNotNull($filaBorrador['edit_url']);
        $this->assertNotNull($filaBorrador['delete_url']);

        $filaEmitida = $filas[$emitida->id];
        $this->assertFalse($filaEmitida['es_borrador']);
        $this->assertNotEquals('Borrador', $filaEmitida['identificador']);
        $this->assertNull($filaEmitida['emitir_url']);
        $this->assertNull($filaEmitida['edit_url']);
        $this->assertNull($filaEmitida['delete_url']);
    }

    public function test_emitir_registra_exactamente_un_evento_emitida(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $factura = $this->facturaBorradorValida($tenant);

        $this->loginAs($user);
        $this->post("/facturas/{$factura->id}/emitir");

        $eventos = FacturaEvento::where('factura_id', $factura->id)->where('tipo_evento', 'emitida')->get();

        $this->assertCount(1, $eventos);
        $this->assertNotNull($eventos->first()->ocurrido_at);
    }
}
