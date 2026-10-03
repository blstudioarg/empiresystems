<?php

namespace Tests\Feature\Caja;

use App\Models\CajaSesion;
use App\Models\Factura;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\TicketPago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * Principio I (NON-NEGOTIABLE): la caja de un negocio no se ve, no se cierra y no recibe ventas de
 * otro. Test-first.
 */
class CajaTenantIsolationTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    /** @return array{0: Tenant, 1: User} */
    private function tenantConUsuario(): array
    {
        $tenant = Tenant::factory()->create();
        Serie::factory()->simplificada()->for($tenant, 'tenant')->create();
        $rol = $this->crearRol($tenant, 'Caja', ['ver-pos', 'ver-pos-crear', 'ver-pos-caja']);

        return [$tenant, $this->usuarioConRol($tenant, $rol)];
    }

    private function ticket(float $precio = 10.0): array
    {
        return ['lineas' => [['concepto' => 'Menú', 'cantidad' => 1, 'precio_unitario' => $precio, 'tipo_impositivo' => 10]]];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarPermisos();
    }

    public function test_la_caja_abierta_de_a_no_sirve_para_cobrar_en_b(): void
    {
        [, $userA] = $this->tenantConUsuario();
        [, $userB] = $this->tenantConUsuario();

        $this->loginAs($userA);
        $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '10'])->assertCreated();
        $sesionA = CajaSesion::withoutGlobalScopes()->sole();

        $this->loginAs($userB);
        $this->postJson('/pos', $this->ticket())->assertStatus(409)->assertJsonPath('codigo', 'caja_cerrada');
        $this->getJson('/pos/caja')->assertJsonPath('abierta', false);

        $this->assertSame(0, TicketPago::withoutGlobalScopes()->where('caja_sesion_id', $sesionA->id)->count());
        $this->assertSame(0, Factura::withoutGlobalScopes()->count());
    }

    public function test_dos_negocios_pueden_tener_cada_uno_su_caja_abierta_a_la_vez(): void
    {
        [$tenantA, $userA] = $this->tenantConUsuario();
        [$tenantB, $userB] = $this->tenantConUsuario();

        $this->loginAs($userA);
        $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '10'])->assertCreated();
        $this->postJson('/pos', $this->ticket())->assertCreated();

        $this->loginAs($userB);
        $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '20'])->assertCreated();
        $this->postJson('/pos', $this->ticket())->assertCreated();

        $sesionA = CajaSesion::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->sole();
        $sesionB = CajaSesion::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->sole();

        $pagoA = TicketPago::withoutGlobalScopes()->where('tenant_id', $tenantA->id)->sole();
        $pagoB = TicketPago::withoutGlobalScopes()->where('tenant_id', $tenantB->id)->sole();
        $this->assertSame($sesionA->id, (int) $pagoA->caja_sesion_id);
        $this->assertSame($sesionB->id, (int) $pagoB->caja_sesion_id);
    }

    public function test_b_no_puede_cerrar_la_caja_de_a_ni_ver_su_informe(): void
    {
        [, $userA] = $this->tenantConUsuario();
        [, $userB] = $this->tenantConUsuario();

        $this->loginAs($userA);
        $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '10'])->assertCreated();
        $sesionA = CajaSesion::withoutGlobalScopes()->sole();

        $this->loginAs($userB);
        $this->postJson('/pos/caja/cerrar', ['sesion_id' => $sesionA->id, 'efectivo_contado' => '10'])
            ->assertStatus(409)->assertJsonPath('codigo', 'caja_cerrada');
        $this->assertTrue($sesionA->fresh()->estaAbierta());

        // A cierra, y B intenta ver ese informe.
        $this->loginAs($userA);
        $this->postJson('/pos/caja/cerrar', ['sesion_id' => $sesionA->id, 'efectivo_contado' => '10'])->assertOk();

        $this->loginAs($userB);
        $this->get("/pos/caja/sesiones/{$sesionA->id}/informe?formato=ticket")->assertNotFound();
        $this->getJson('/pos/caja/cierres')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_el_resumen_de_a_no_incluye_ventas_ni_movimientos_de_b(): void
    {
        [, $userA] = $this->tenantConUsuario();
        [, $userB] = $this->tenantConUsuario();

        $this->loginAs($userB);
        $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '0'])->assertCreated();
        $this->postJson('/pos', $this->ticket(50))->assertCreated();
        $this->postJson('/pos/caja/movimientos', ['tipo' => 'entrada', 'importe' => '100', 'motivo' => 'Cambio'])->assertCreated();

        $this->loginAs($userA);
        $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '0'])->assertCreated();
        $this->postJson('/pos', $this->ticket(10))->assertCreated();

        $this->getJson('/pos/caja')
            ->assertJsonPath('en_vivo.num_tickets', 1)
            ->assertJsonPath('en_vivo.total_vendido', '11.00')
            ->assertJsonCount(0, 'en_vivo.movimientos');
    }
}
