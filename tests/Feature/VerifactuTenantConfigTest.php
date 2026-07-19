<?php

namespace Tests\Feature;

use App\Enums\EntornoVerifactu;
use App\Models\FacturaEvento;
use App\Models\Tenant;
use App\Models\User;
use App\Support\VerifactuTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifactuTenantConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_flag_activo_por_defecto_es_falso(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertFalse(VerifactuTenant::activo($tenant->id));
    }

    public function test_el_entorno_por_defecto_es_pruebas(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertSame(EntornoVerifactu::Pruebas, VerifactuTenant::entorno($tenant->id));
    }

    public function test_activar_el_flag_en_un_tenant_no_afecta_a_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        VerifactuTenant::activar($tenantA->id, true);

        $this->assertTrue(VerifactuTenant::activo($tenantA->id));
        $this->assertFalse(VerifactuTenant::activo($tenantB->id));
    }

    public function test_cambiar_el_entorno_en_un_tenant_no_afecta_a_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        VerifactuTenant::establecerEntorno($tenantA->id, EntornoVerifactu::Produccion);

        $this->assertSame(EntornoVerifactu::Produccion, VerifactuTenant::entorno($tenantA->id));
        $this->assertSame(EntornoVerifactu::Pruebas, VerifactuTenant::entorno($tenantB->id));
    }

    public function test_activar_y_desactivar_el_flag_desde_la_configuracion(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->put('/configuracion/verifactu', ['activo' => true, 'entorno' => 'pruebas'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(VerifactuTenant::activo($tenant->id));

        $this->put('/configuracion/verifactu', ['activo' => false, 'entorno' => 'pruebas'])
            ->assertSessionHasNoErrors();
        $this->assertFalse(VerifactuTenant::activo($tenant->id));
    }

    public function test_bloquea_el_cambio_de_entorno_con_la_cadena_ya_iniciada(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        // Simula una cadena ya iniciada: un evento verifactu_alta con huella.
        FacturaEvento::create([
            'tenant_id' => $tenant->id,
            'factura_id' => null,
            'tipo_evento' => 'verifactu_alta',
            'detalle' => ['huella' => 'ABC', 'huella_anterior' => '', 'entorno' => 'pruebas'],
            'huella' => 'ABC',
            'ocurrido_at' => now(),
        ]);

        $this->assertTrue(VerifactuTenant::cadenaIniciada($tenant->id));

        $this->loginAs($user);

        $response = $this->put('/configuracion/verifactu', ['activo' => true, 'entorno' => 'produccion']);

        $response->assertSessionHas('error');
        $this->assertSame(EntornoVerifactu::Pruebas, VerifactuTenant::entorno($tenant->id));
    }
}
