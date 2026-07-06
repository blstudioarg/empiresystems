<?php

namespace Tests\Feature;

use App\Enums\EstadoCobro;
use App\Enums\EstadoFactura;
use App\Enums\TipoRectificacion;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagoRectificativaDiferenciasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Factura, 1: Factura} [original rectificada, rectificativa]
     */
    private function crearParRectificadoPorDiferencias(Tenant $tenant, float $totalOriginal, float $totalRectificativa): array
    {
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $original = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'total' => $totalOriginal,
            'estado' => EstadoFactura::Rectificada,
        ]);

        $rectificativa = Factura::factory()->emitida()->rectificativa()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'factura_rectificada_id' => $original->id,
            'tipo_rectificacion' => TipoRectificacion::Diferencias,
            'motivo_rectificacion' => 'Ajuste de prueba',
            'total' => $totalRectificativa,
        ]);

        return [$original, $rectificativa];
    }

    public function test_saldo_de_original_rectificada_por_diferencias_es_el_neto(): void
    {
        $tenant = Tenant::factory()->create();
        [$original] = $this->crearParRectificadoPorDiferencias($tenant, 100.00, -25.00);

        $this->assertSame(75.0, $original->saldoPendiente());
        $this->assertSame(EstadoCobro::Pendiente, $original->estadoCobro());
    }

    public function test_se_puede_cobrar_el_neto_de_una_original_rectificada_por_diferencias(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        [$original] = $this->crearParRectificadoPorDiferencias($tenant, 100.00, -25.00);

        $this->loginAs($user);

        $response = $this->postJson("/facturas/{$original->id}/pagos", [
            'fecha' => now()->toDateString(),
            'importe' => 75.00,
            'metodo' => 'transferencia',
        ]);

        $response->assertCreated();

        $original->refresh();
        $this->assertSame(0.0, $original->saldoPendiente());
        $this->assertSame(EstadoCobro::Cobrada, $original->estadoCobro());
    }

    public function test_el_pago_no_puede_exceder_el_neto_rectificado(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        [$original] = $this->crearParRectificadoPorDiferencias($tenant, 100.00, -25.00);

        $this->loginAs($user);

        $response = $this->postJson("/facturas/{$original->id}/pagos", [
            'fecha' => now()->toDateString(),
            'importe' => 100.00,
            'metodo' => 'transferencia',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('pagos', 0);
    }

    /**
     * @return array{0: Factura, 1: Factura} [original rectificada, rectificativa]
     */
    private function crearParRectificadoPorSustitucion(Tenant $tenant, float $totalOriginal, float $totalRectificativa): array
    {
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $original = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'total' => $totalOriginal,
            'estado' => EstadoFactura::Rectificada,
        ]);

        $rectificativa = Factura::factory()->emitida()->rectificativa()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'factura_rectificada_id' => $original->id,
            'tipo_rectificacion' => TipoRectificacion::Sustitucion,
            'motivo_rectificacion' => 'Sustitución de prueba',
            'total' => $totalRectificativa,
        ]);

        return [$original, $rectificativa];
    }

    public function test_el_cobro_de_una_rectificada_por_sustitucion_se_hace_desde_la_original(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        [$original] = $this->crearParRectificadoPorSustitucion($tenant, 100.00, 75.00);

        // El importe cobrable de la original pasa a ser el total de la sustituta.
        $this->assertSame(75.0, $original->totalCobrable());
        $this->assertSame(75.0, $original->saldoPendiente());

        $this->loginAs($user);

        $response = $this->postJson("/facturas/{$original->id}/pagos", [
            'fecha' => now()->toDateString(),
            'importe' => 75.00,
            'metodo' => 'transferencia',
        ]);

        $response->assertCreated();

        $original->refresh();
        $this->assertSame(0.0, $original->saldoPendiente());
        $this->assertSame(EstadoCobro::Cobrada, $original->estadoCobro());
        $this->assertDatabaseHas('pagos', ['factura_id' => $original->id, 'importe' => 75.00]);
    }

    public function test_el_pago_de_una_rectificada_por_sustitucion_no_puede_exceder_el_total_sustituto(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        [$original] = $this->crearParRectificadoPorSustitucion($tenant, 100.00, 75.00);

        $this->loginAs($user);

        $response = $this->postJson("/facturas/{$original->id}/pagos", [
            'fecha' => now()->toDateString(),
            'importe' => 100.00,
            'metodo' => 'transferencia',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_la_rectificativa_por_sustitucion_no_admite_registrar_pagos(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        [, $rectificativa] = $this->crearParRectificadoPorSustitucion($tenant, 100.00, 75.00);

        $this->loginAs($user);

        $response = $this->postJson("/facturas/{$rectificativa->id}/pagos", [
            'fecha' => now()->toDateString(),
            'importe' => 75.00,
            'metodo' => 'transferencia',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_listado_expone_urls_de_cobro_para_la_original_rectificada_por_diferencias(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        [$original, $rectificativa] = $this->crearParRectificadoPorDiferencias($tenant, 100.00, -25.00);

        $this->loginAs($user);

        $response = $this->getJson('/facturas');
        $response->assertOk();

        $filaOriginal = collect($response->json('data'))->firstWhere('id', $original->id);
        $this->assertNotNull($filaOriginal['cobros_url']);
        $this->assertNotNull($filaOriginal['pago_url']);
        $this->assertSame('75.00', $filaOriginal['saldo_pendiente']);

        // La rectificativa negativa nunca es cobrable por sí misma: ni cobros ni pago.
        // El cobro se gestiona íntegramente sobre la original.
        $filaRectificativa = collect($response->json('data'))->firstWhere('id', $rectificativa->id);
        $this->assertNull($filaRectificativa['cobros_url']);
        $this->assertNull($filaRectificativa['pago_url']);
    }

    public function test_listado_expone_total_efectivo_de_una_original_rectificada_por_sustitucion(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        [$original, $rectificativa] = $this->crearParRectificadoPorSustitucion($tenant, 100.00, 75.00);

        $this->loginAs($user);

        $response = $this->getJson('/facturas');
        $response->assertOk();

        $filaOriginal = collect($response->json('data'))->firstWhere('id', $original->id);
        $this->assertTrue($filaOriginal['es_rectificada']);
        $this->assertSame('100.00', $filaOriginal['total']);
        $this->assertSame('75.00', $filaOriginal['total_efectivo']);
        $this->assertNotNull($filaOriginal['cobros_url']);
        $this->assertNotNull($filaOriginal['pago_url']);

        // La rectificativa por sustitución no se cobra por sí misma desde la UI.
        $filaRectificativa = collect($response->json('data'))->firstWhere('id', $rectificativa->id);
        $this->assertNull($filaRectificativa['cobros_url']);
        $this->assertNull($filaRectificativa['pago_url']);
    }

    public function test_importe_total_no_cuenta_dos_veces_una_sustitucion(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->crearParRectificadoPorSustitucion($tenant, 100.00, 75.00);

        $this->loginAs($user);

        $response = $this->getJson('/facturas');
        $response->assertOk();

        // 75 (importe efectivo de la sustitución), no 175 (original 100 + sustituta 75).
        $this->assertSame('75.00', $response->json('totales.importe_total'));
    }

    public function test_importe_total_suma_el_neto_en_diferencias(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->crearParRectificadoPorDiferencias($tenant, 100.00, -25.00);

        $this->loginAs($user);

        $response = $this->getJson('/facturas');
        $response->assertOk();

        $this->assertSame('75.00', $response->json('totales.importe_total'));
    }

    public function test_importe_total_excluye_borradores_y_anuladas(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        Factura::factory()->emitida()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'total' => 200.00]);
        Factura::factory()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'total' => 999.00]); // borrador
        Factura::factory()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'total' => 999.00, 'estado' => EstadoFactura::Anulada]);

        $this->loginAs($user);

        $response = $this->getJson('/facturas');
        $response->assertOk();

        $this->assertSame('200.00', $response->json('totales.importe_total'));
    }

    public function test_la_rectificativa_por_diferencias_no_admite_registrar_pagos(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        [, $rectificativa] = $this->crearParRectificadoPorDiferencias($tenant, 100.00, -25.00);

        $this->loginAs($user);

        $response = $this->postJson("/facturas/{$rectificativa->id}/pagos", [
            'fecha' => now()->toDateString(),
            'importe' => 10.00,
            'metodo' => 'transferencia',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('pagos', 0);
    }
}
