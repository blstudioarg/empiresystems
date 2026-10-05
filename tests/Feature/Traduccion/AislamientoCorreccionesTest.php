<?php

namespace Tests\Feature\Traduccion;

use App\Models\Tenant;
use App\Models\Traduccion;
use App\Models\TraduccionCorreccion;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * Principio I / FR-018: la corrección manual de una traducción es del tenant que la hizo. Otro
 * tenant en el mismo idioma no la ve (ni en el POS, ni en el listado), ni puede editarla ni
 * borrarla por hash. Cada escenario en su propio método: el `Translator` cachea lo cargado por
 * idioma y mezclar dos tenants en el mismo método enmascararía una fuga.
 */
class AislamientoCorreccionesTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    /** @return array{0: Tenant, 1: Tenant, 2: string} */
    private function escenario(): array
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        Traduccion::factory()->traducida('收款')->create(['texto' => 'Cobrar']);
        $hash = Traduccion::hashDe('Cobrar');

        TraduccionCorreccion::factory()->create([
            'tenant_id' => $tenantA->id,
            'hash' => $hash,
            'texto' => 'Cobrar',
            'traduccion' => '结账',
        ]);

        return [$tenantA, $tenantB, $hash];
    }

    public function test_la_correccion_de_a_no_se_carga_para_b(): void
    {
        [, $tenantB] = $this->escenario();

        tenancy()->initialize($tenantB);
        app()->setLocale('zh');

        $this->assertSame('收款', __('Cobrar'));
    }

    public function test_la_correccion_de_a_si_se_carga_para_a(): void
    {
        [$tenantA] = $this->escenario();

        tenancy()->initialize($tenantA);
        app()->setLocale('zh');

        $this->assertSame('结账', __('Cobrar'));
    }

    public function test_el_modelo_no_ve_correcciones_de_otro_tenant(): void
    {
        [, $tenantB] = $this->escenario();

        tenancy()->initialize($tenantB);

        $this->assertCount(0, TraduccionCorreccion::all());
    }

    private function loginEnB(Tenant $tenantB): void
    {
        $this->sembrarPermisos();
        ConfigPos::guardar($tenantB->id, ['idioma' => 'zh']);
        $usuario = $this->usuarioConRol($tenantB, $this->crearRol($tenantB, 'Admin B', ['ver-configuracion']));
        $this->loginAs($usuario);
    }

    public function test_el_listado_de_b_no_muestra_la_correccion_de_a(): void
    {
        [, $tenantB] = $this->escenario();
        $this->loginEnB($tenantB);

        $fila = collect($this->getJson('/configuracion/pos/traducciones')->assertOk()->json('data'))
            ->firstWhere('texto', 'Cobrar');

        $this->assertSame('收款', $fila['traduccion']);
        $this->assertSame('automatica', $fila['origen']);
    }

    public function test_b_no_puede_modificar_ni_borrar_la_correccion_de_a(): void
    {
        [$tenantA, $tenantB, $hash] = $this->escenario();
        $this->loginEnB($tenantB);

        // Borrar: B no tiene corrección propia → 404, la de A intacta.
        $this->deleteJson("/configuracion/pos/traducciones/{$hash}")->assertNotFound();

        // Guardar: crea la de B, no toca la de A.
        $this->putJson("/configuracion/pos/traducciones/{$hash}", ['traduccion' => '买单'])->assertOk();

        $deA = TraduccionCorreccion::query()->withoutGlobalScopes()->where('tenant_id', $tenantA->id)->sole();
        $this->assertSame('结账', $deA->traduccion);
        $this->assertSame(2, TraduccionCorreccion::query()->withoutGlobalScopes()->count());
    }
}
