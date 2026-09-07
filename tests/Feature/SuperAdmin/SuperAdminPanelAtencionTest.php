<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\AccionLogActividad;
use App\Enums\EstadoUsuario;
use App\Enums\ResultadoLogActividad;
use App\Models\LogActividad;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * US3 (037-super-admin-panel-aislado): tenants que requieren atención. Contrato de motivos en
 * specs/037-super-admin-panel-aislado/contracts/panel-home.md.
 */
class SuperAdminPanelAtencionTest extends TestCase
{
    use RefreshDatabase;

    private function registrarActividad(Tenant $tenant, Carbon $ocurridoAt): void
    {
        LogActividad::create([
            'tenant_id' => $tenant->id,
            'usuario_id' => null,
            'usuario_nombre' => 'Sistema',
            'accion' => AccionLogActividad::Modificacion,
            'resultado' => ResultadoLogActividad::Exito,
            'ip_origen' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'entidad_tipo' => null,
            'entidad_id' => null,
            'descripcion' => 'Actividad de prueba.',
            'ocurrido_at' => $ocurridoAt,
        ]);
    }

    public function test_tenant_desactivado_aparece_con_ese_motivo(): void
    {
        $tenant = Tenant::factory()->create(['activo' => false]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'estado' => EstadoUsuario::Aprobado,
            'activo' => true,
        ]);
        $this->registrarActividad($tenant, now());

        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $datos = $this->get(route('super_admin.home'))->viewData('datos');

        $fila = collect($datos['atencion'])->firstWhere('id', $tenant->id);
        $this->assertNotNull($fila);
        $this->assertSame(['desactivado'], $fila['motivos']);
    }

    public function test_tenant_sin_usuarios_que_puedan_entrar_aparece_con_ese_motivo(): void
    {
        $tenant = Tenant::factory()->create(['activo' => true]);
        // Usuario pendiente de aprobación: no cuenta como "puede entrar".
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'estado' => EstadoUsuario::Pendiente,
            'activo' => false,
        ]);
        $this->registrarActividad($tenant, now());

        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $datos = $this->get(route('super_admin.home'))->viewData('datos');

        $fila = collect($datos['atencion'])->firstWhere('id', $tenant->id);
        $this->assertNotNull($fila);
        $this->assertSame(['sin_usuarios'], $fila['motivos']);
    }

    public function test_tenant_sin_actividad_en_30_dias_aparece_con_ese_motivo(): void
    {
        $tenant = Tenant::factory()->create(['activo' => true]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'estado' => EstadoUsuario::Aprobado,
            'activo' => true,
        ]);
        $this->registrarActividad($tenant, now()->subDays(45));

        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $datos = $this->get(route('super_admin.home'))->viewData('datos');

        $fila = collect($datos['atencion'])->firstWhere('id', $tenant->id);
        $this->assertNotNull($fila);
        $this->assertSame(['sin_actividad'], $fila['motivos']);
    }

    public function test_tenant_sin_ninguna_actividad_registrada_tambien_cuenta_como_sin_actividad(): void
    {
        $tenant = Tenant::factory()->create(['activo' => true]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'estado' => EstadoUsuario::Aprobado,
            'activo' => true,
        ]);
        // Sin ningún LogActividad para este tenant.

        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $datos = $this->get(route('super_admin.home'))->viewData('datos');

        $fila = collect($datos['atencion'])->firstWhere('id', $tenant->id);
        $this->assertNotNull($fila);
        $this->assertSame(['sin_actividad'], $fila['motivos']);
    }

    public function test_tenant_con_varios_motivos_aparece_una_sola_vez_con_todos(): void
    {
        $tenant = Tenant::factory()->create(['activo' => false]);
        // Sin usuarios aprobados+activos y sin ningún registro de actividad: 3 motivos a la vez.

        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $datos = $this->get(route('super_admin.home'))->viewData('datos');

        $filas = collect($datos['atencion'])->where('id', $tenant->id);
        $this->assertCount(1, $filas);
        $this->assertEqualsCanonicalizing(
            ['desactivado', 'sin_usuarios', 'sin_actividad'],
            $filas->first()['motivos']
        );
    }

    public function test_sin_ningun_tenant_que_cumpla_criterios_el_bloque_queda_vacio(): void
    {
        $tenant = Tenant::factory()->create(['activo' => true]);
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'estado' => EstadoUsuario::Aprobado,
            'activo' => true,
        ]);
        $this->registrarActividad($tenant, now());

        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);
        $this->loginAs($superAdmin);

        $datos = $this->get(route('super_admin.home'))->viewData('datos');

        $this->assertSame([], $datos['atencion']);
    }
}
