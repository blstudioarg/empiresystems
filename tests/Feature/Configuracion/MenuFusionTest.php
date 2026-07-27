<?php

namespace Tests\Feature\Configuracion;

use App\Models\Configuracion;
use App\Models\Tenant;
use App\Support\MenuTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reglas de fusión catálogo + personalización (data-model.md §3): FR-007, FR-010, FR-017, FR-018.
 * `MenuTenant::estructura()` debe ser determinista y total para cualquier JSON guardado, incluso
 * corrupto o con claves inventadas (no lanza excepción).
 */
class MenuFusionTest extends TestCase
{
    use RefreshDatabase;

    private function guardarValorCrudo(int $tenantId, ?string $valor): void
    {
        if ($valor === null) {
            return;
        }

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => MenuTenant::CLAVE],
            ['valor' => $valor, 'tipo' => 'json', 'grupo' => 'menu'],
        );

        // El helper escribe la fila directo (bypaseando MenuTenant::guardar()) para simular datos
        // guardados a mano/corruptos: hay que invalidar la memoización por tenant_id a mano, igual
        // que si fuera otra vía de escritura fuera de la clase (ver MenuTenant::invalidarCache()).
        MenuTenant::invalidarCache($tenantId);
    }

    public function test_sin_personalizacion_devuelve_el_catalogo_por_defecto(): void
    {
        $tenant = Tenant::factory()->create();

        $estructura = MenuTenant::estructura($tenant->id);

        $this->assertCount(10, $estructura);
        $this->assertSame('inicio', $estructura[0]['clave']);
        $this->assertSame('Inicio', $estructura[0]['etiqueta']);
        $this->assertSame('Inicio', $estructura[0]['etiqueta_defecto']);
    }

    public function test_json_vacio_no_rompe_y_devuelve_el_catalogo(): void
    {
        $tenant = Tenant::factory()->create();
        $this->guardarValorCrudo($tenant->id, '');

        $estructura = MenuTenant::estructura($tenant->id);

        $this->assertCount(10, $estructura);
        $this->assertSame('Inicio', $estructura[0]['etiqueta']);
    }

    public function test_json_corrupto_no_rompe_y_devuelve_el_catalogo(): void
    {
        $tenant = Tenant::factory()->create();
        $this->guardarValorCrudo($tenant->id, '{esto no es json valido');

        $estructura = MenuTenant::estructura($tenant->id);

        $this->assertCount(10, $estructura);
        $this->assertSame('Inicio', $estructura[0]['etiqueta']);
    }

    public function test_clave_inexistente_en_etiquetas_y_orden_se_descarta(): void
    {
        $tenant = Tenant::factory()->create();
        $this->guardarValorCrudo($tenant->id, json_encode([
            'etiquetas' => ['clave-inventada' => 'Fantasma', 'clientes' => 'Pacientes'],
            'orden' => ['_raiz' => ['clave-inventada', 'clientes', 'facturas']],
        ]));

        $estructura = MenuTenant::estructura($tenant->id);
        $claves = array_column($estructura, 'clave');

        $this->assertNotContains('clave-inventada', $claves);
        $this->assertSame('clientes', $estructura[0]['clave']);
        $this->assertSame('Pacientes', $estructura[0]['etiqueta']);
    }

    public function test_elemento_del_catalogo_ausente_del_orden_guardado_queda_al_final(): void
    {
        $tenant = Tenant::factory()->create();
        $this->guardarValorCrudo($tenant->id, json_encode([
            'orden' => ['_raiz' => ['facturas']],
        ]));

        $estructura = MenuTenant::estructura($tenant->id);
        $claves = array_column($estructura, 'clave');

        $this->assertSame('facturas', $claves[0]);
        // El resto conserva su orden relativo original del catálogo.
        $this->assertSame(
            ['inicio', 'control-fichaje', 'clientes', 'crm', 'stock', 'pos', 'archivos', 'marketing', 'usuarios'],
            array_slice($claves, 1),
        );
    }

    public function test_etiqueta_en_blanco_cae_al_valor_por_defecto(): void
    {
        $tenant = Tenant::factory()->create();
        $this->guardarValorCrudo($tenant->id, json_encode([
            'etiquetas' => ['clientes' => '   '],
        ]));

        $estructura = MenuTenant::estructura($tenant->id);
        $grupo = collect($estructura)->firstWhere('clave', 'clientes');

        $this->assertSame('Clientes', $grupo['etiqueta']);
    }

    public function test_el_orden_se_aplica_tambien_al_nivel_de_hijos(): void
    {
        $tenant = Tenant::factory()->create();
        MenuTenant::guardar($tenant->id, [], ['control-fichaje' => ['alertas', 'fichar']]);

        $grupo = collect(MenuTenant::estructura($tenant->id))->firstWhere('clave', 'control-fichaje');
        $clavesHijos = array_column($grupo['hijos'], 'clave');

        $this->assertSame('alertas', $clavesHijos[0]);
        $this->assertSame('fichar', $clavesHijos[1]);
        $this->assertSame(
            ['mi-jornada', 'jornada', 'calendario', 'miembros', 'horarios'],
            array_slice($clavesHijos, 2),
        );
    }

    public function test_memoizacion_no_mezcla_dos_tenants_en_el_mismo_request(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        MenuTenant::guardar($tenantA->id, ['clientes' => 'Pacientes'], []);

        $estructuraA1 = MenuTenant::estructura($tenantA->id);
        $estructuraB = MenuTenant::estructura($tenantB->id);
        $estructuraA2 = MenuTenant::estructura($tenantA->id);

        $this->assertSame('Pacientes', collect($estructuraA1)->firstWhere('clave', 'clientes')['etiqueta']);
        $this->assertSame('Clientes', collect($estructuraB)->firstWhere('clave', 'clientes')['etiqueta']);
        $this->assertSame($estructuraA1, $estructuraA2);
    }
}
