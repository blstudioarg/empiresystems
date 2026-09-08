<?php

namespace Tests\Feature\Asistente;

use App\Models\Configuracion;
use App\Models\LogActividad;
use App\Models\Tenant;
use App\Models\User;
use App\Support\IaTenant;
use App\Support\RetencionAsistenteTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class ConfiguracionIaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardar_clave_la_persiste_cifrada(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->put('/configuracion/ia', ['api_key' => 'sk-ant-secreta-12345'])
            ->assertRedirect(route('configuracion.show'));

        $fila = Configuracion::query()
            ->where('tenant_id', $tenant->id)
            ->where('clave', IaTenant::CLAVE_API_KEY)
            ->first();

        $this->assertNotNull($fila);
        $this->assertNotSame('sk-ant-secreta-12345', $fila->valor, 'La clave no debe guardarse en texto plano.');
        $this->assertSame('sk-ant-secreta-12345', Crypt::decryptString($fila->valor));
    }

    public function test_la_respuesta_nunca_expone_la_clave_completa(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        IaTenant::guardarApiKey('sk-ant-supersecreta-9999', $tenant->id);
        $this->loginAs($user);

        $response = $this->get('/configuracion');

        $response->assertOk();
        $response->assertDontSee('sk-ant-supersecreta-9999');
        $response->assertSee('sk-…9999');
    }

    public function test_quitar_clave_la_borra(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        IaTenant::guardarApiKey('sk-ant-a-borrar', $tenant->id);
        $this->loginAs($user);

        // Quitar la clave es una acción explícita desde la feature 045: antes bastaba con enviar
        // `api_key` vacía, pero el campo es de tipo password y viaja vacío en cada guardado, así que
        // ese contrato hacía que guardar el plazo de retención borrase la clave sin querer.
        $this->put('/configuracion/ia', ['api_key' => '', 'quitar_clave' => '1']);

        $this->assertFalse(IaTenant::configurada($tenant->id));
    }

    public function test_guardar_solo_el_plazo_de_retencion_no_borra_la_clave(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        IaTenant::guardarApiKey('sk-ant-que-debe-sobrevivir', $tenant->id);
        $this->loginAs($user);

        $this->put('/configuracion/ia', ['api_key' => '', 'retencion_dias' => 30]);

        $this->assertTrue(IaTenant::configurada($tenant->id));
        $this->assertSame(30, RetencionAsistenteTenant::dias($tenant->id));
    }

    public function test_endpoints_exigen_ver_configuracion(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]); // rol base, sin ver-configuracion
        $this->loginAs($user);

        $this->put('/configuracion/ia', ['api_key' => 'sk-ant-x'])->assertForbidden();
        $this->post('/configuracion/ia/probar')->assertForbidden();
    }

    public function test_aislamiento_la_clave_de_un_tenant_es_invisible_desde_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        IaTenant::guardarApiKey('sk-ant-tenant-A', $tenantA->id);

        $this->assertTrue(IaTenant::configurada($tenantA->id));
        $this->assertFalse(IaTenant::configurada($tenantB->id));
        $this->assertSame('', IaTenant::apiKey($tenantB->id));
    }

    public function test_guardar_registra_en_logs_actividad(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->put('/configuracion/ia', ['api_key' => 'sk-ant-logueada']);

        $this->assertTrue(
            LogActividad::query()
                ->where('tenant_id', $tenant->id)
                ->where('descripcion', 'like', '%asistente IA%')
                ->exists()
        );
    }
}
