<?php

namespace Tests\Feature\Caja;

use App\Models\CajaSesion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * US1 — abrir la caja (FR-001, FR-002, FR-003, FR-022). Test-first (Principio IV): el fondo que
 * queda guardado es una cifra de arqueo, así que lo decide el servidor.
 */
class AperturaCajaTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    private Tenant $tenant;

    /** @param  list<string>  $permisos */
    private function usuario(array $permisos = ['ver-pos-caja']): User
    {
        $this->sembrarPermisos();
        $this->tenant ??= Tenant::factory()->create();
        $rol = $this->crearRol($this->tenant, 'Rol '.implode('-', $permisos), $permisos);

        return $this->usuarioConRol($this->tenant, $rol);
    }

    public function test_abre_la_caja_con_un_fondo_en_importe(): void
    {
        $user = $this->usuario();
        $this->loginAs($user);

        $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '150.00'])
            ->assertCreated()
            ->assertJsonPath('sesion.fondo_inicial', '150.00')
            ->assertJsonPath('sesion.abierta_por', $user->name);

        $sesion = CajaSesion::withoutGlobalScopes()->sole();
        $this->assertSame(CajaSesion::ESTADO_ABIERTA, $sesion->estado);
        $this->assertSame($this->tenant->id, (int) $sesion->tenant_id);
        $this->assertSame($user->id, (int) $sesion->abierta_por);
        $this->assertSame('150.00', (string) $sesion->fondo_inicial);
        $this->assertNotNull($sesion->abierta_at);
    }

    public function test_con_conteo_el_fondo_lo_calcula_el_servidor_e_ignora_el_total_enviado(): void
    {
        $this->loginAs($this->usuario());

        // 2 × 50 € + 10 × 2 € + 5 × 10 cént. = 120,50 €. El total enviado es mentira a propósito.
        $this->postJson('/pos/caja/abrir', [
            'conteo' => ['5000' => 2, '200' => 10, '10' => 5],
            'fondo_inicial' => '999.99',
        ])->assertCreated()->assertJsonPath('sesion.fondo_inicial', '120.50');

        $sesion = CajaSesion::withoutGlobalScopes()->sole();
        $this->assertSame('120.50', (string) $sesion->fondo_inicial);
        $this->assertSame(['5000' => 2, '200' => 10, '10' => 5], $sesion->conteo_apertura);
    }

    public function test_un_fondo_de_cero_es_valido(): void
    {
        $this->loginAs($this->usuario());

        $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '0'])->assertCreated();
    }

    public function test_fondo_negativo_o_denominacion_inexistente_responde_422(): void
    {
        $this->loginAs($this->usuario());

        $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '-5'])->assertUnprocessable();
        $this->postJson('/pos/caja/abrir', ['conteo' => ['300' => 1]])->assertUnprocessable();
        $this->postJson('/pos/caja/abrir', [])->assertUnprocessable();

        $this->assertSame(0, CajaSesion::withoutGlobalScopes()->count());
    }

    public function test_una_segunda_apertura_responde_409_con_la_sesion_existente(): void
    {
        $this->loginAs($this->usuario());

        $primera = $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '100'])->assertCreated()->json('sesion.id');

        $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '50'])
            ->assertStatus(409)
            ->assertJsonPath('codigo', 'caja_ya_abierta')
            ->assertJsonPath('sesion.id', $primera);

        $this->assertSame(1, CajaSesion::withoutGlobalScopes()->where('estado', 'abierta')->count());
    }

    public function test_el_indice_unico_impide_dos_sesiones_abiertas_aunque_se_salte_el_servicio(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $datos = ['tenant_id' => $tenant->id, 'estado' => 'abierta', 'abierta_marca' => 1, 'abierta_por' => $user->id, 'abierta_at' => now()];

        CajaSesion::withoutGlobalScopes()->create($datos);

        $this->expectException(QueryException::class);
        CajaSesion::withoutGlobalScopes()->create($datos);
    }

    public function test_quien_solo_crea_tickets_tambien_puede_abrir_la_caja(): void
    {
        $this->loginAs($this->usuario(['ver-pos-crear']));

        $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '20'])->assertCreated();
    }

    public function test_sin_permiso_de_caja_ni_de_crear_tickets_responde_403(): void
    {
        $this->loginAs($this->usuario(['ver-pos']));

        $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '20'])->assertForbidden();
        $this->getJson('/pos/caja')->assertForbidden();
    }

    public function test_el_estado_refleja_la_caja_cerrada_y_luego_abierta(): void
    {
        $this->loginAs($this->usuario());

        $this->getJson('/pos/caja')->assertOk()->assertJsonPath('abierta', false);

        $this->postJson('/pos/caja/abrir', ['fondo_inicial' => '80'])->assertCreated();

        $this->getJson('/pos/caja')
            ->assertOk()
            ->assertJsonPath('abierta', true)
            ->assertJsonPath('sesion.fondo_inicial', '80.00')
            ->assertJsonPath('sesion.abierta_dia_anterior', false);
    }

    public function test_avisa_si_la_caja_lleva_abierta_desde_un_dia_anterior(): void
    {
        $user = $this->usuario();
        CajaSesion::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'estado' => 'abierta', 'abierta_marca' => 1,
            'abierta_por' => $user->id, 'abierta_at' => now()->subDays(1)->setTime(9, 0),
        ]);
        $this->loginAs($user);

        $this->getJson('/pos/caja')->assertJsonPath('sesion.abierta_dia_anterior', true);
    }
}
