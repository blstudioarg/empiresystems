<?php

namespace Tests\Feature\Configuracion;

use App\Models\Tenant;
use App\Support\MenuTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Principio I (NON-NEGOTIABLE), FR-012, SC-004: con ≥2 tenants, la personalización guardada por
 * el tenant A no es legible ni modificable desde el tenant B, y el menú de B sigue en sus valores
 * por defecto. Test-first: debe fallar antes de que exista `App\Support\MenuTenant` (T005).
 */
class MenuAislamientoTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_personalizacion_de_un_tenant_no_es_visible_desde_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        MenuTenant::guardar($tenantA->id, ['clientes' => 'Pacientes'], ['_raiz' => ['facturas', 'clientes', 'inicio']]);

        $estructuraA = MenuTenant::estructura($tenantA->id);
        $estructuraB = MenuTenant::estructura($tenantB->id);

        $grupoClientesA = collect($estructuraA)->firstWhere('clave', 'clientes');
        $grupoClientesB = collect($estructuraB)->firstWhere('clave', 'clientes');

        $this->assertSame('Pacientes', $grupoClientesA['etiqueta'], 'El tenant A debe ver su etiqueta personalizada.');
        $this->assertSame('Clientes', $grupoClientesB['etiqueta'], 'El tenant B no debe heredar la personalización de A.');

        $this->assertSame('facturas', $estructuraA[0]['clave'], 'El tenant A debe ver su orden personalizado.');
        $this->assertNotSame('facturas', $estructuraB[0]['clave'], 'El tenant B debe conservar el orden por defecto.');
    }

    public function test_restaurar_un_tenant_no_afecta_al_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        MenuTenant::guardar($tenantA->id, ['clientes' => 'Pacientes'], []);
        MenuTenant::guardar($tenantB->id, ['clientes' => 'Compradores'], []);

        MenuTenant::restaurar($tenantA->id);

        $grupoA = collect(MenuTenant::estructura($tenantA->id))->firstWhere('clave', 'clientes');
        $grupoB = collect(MenuTenant::estructura($tenantB->id))->firstWhere('clave', 'clientes');

        $this->assertSame('Clientes', $grupoA['etiqueta'], 'Tras restaurar, A vuelve al valor por defecto.');
        $this->assertSame('Compradores', $grupoB['etiqueta'], 'B conserva su propia personalización.');
    }
}
