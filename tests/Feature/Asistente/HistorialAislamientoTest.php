<?php

namespace Tests\Feature\Asistente;

use App\Models\AsistenteConversacion;
use App\Models\AsistenteMensaje;
use App\Models\Tenant;
use App\Models\User;
use App\Support\IaTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Principio I (NON-NEGOTIABLE): el historial del asistente no puede cruzar ninguna frontera, ni
 * entre empresas ni entre personas de la misma empresa. Una conversación con el asistente puede
 * contener cualquier cosa del negocio, así que una fuga aquí es tan grave como una de facturas.
 *
 * Los ids ajenos responden **404 y no 403** a propósito: un 403 confirmaría que el id existe, y eso
 * ya es una filtración (mismo criterio que `CompraDocumentoAislamientoTest`).
 *
 * Test-first (Principio IV): este fichero se escribe antes que las migraciones, los modelos y el
 * controlador, y debe fallar hasta que existan.
 */
class HistorialAislamientoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantConUsuario(): array
    {
        $tenant = Tenant::factory()->create();
        IaTenant::guardarApiKey('sk-de-prueba', $tenant->id);
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        return [$tenant, $user];
    }

    private function conversacionDe(Tenant $tenant, User $user, string $titulo = 'Hilo privado'): AsistenteConversacion
    {
        $conversacion = AsistenteConversacion::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'titulo' => $titulo,
            'ultima_actividad_en' => now(),
        ]);

        // Un hilo entra en el historial cuando la persona escribió algo (FR-008): sin este
        // mensaje la conversación existe pero no se lista, y el fixture no probaría nada.
        $conversacion->mensajes()->create([
            'tenant_id' => $tenant->id,
            'rol' => 'user',
            'contenido' => 'mensaje de '.$titulo,
        ]);

        return $conversacion;
    }

    // --- Entre tenants ------------------------------------------------------

    public function test_una_conversacion_de_otro_tenant_no_aparece_en_el_listado(): void
    {
        [$tenantA, $userA] = $this->tenantConUsuario();
        [$tenantB, $userB] = $this->tenantConUsuario();

        $this->conversacionDe($tenantA, $userA, 'Secreto de A');
        $this->conversacionDe($tenantB, $userB, 'Cosa de B');

        $this->loginAs($userB);

        $respuesta = $this->getJson('/asistente/conversaciones')->assertOk();

        $titulos = collect($respuesta->json('conversaciones'))->pluck('titulo');

        $this->assertContains('Cosa de B', $titulos);
        $this->assertNotContains('Secreto de A', $titulos);
    }

    public function test_abrir_por_id_una_conversacion_de_otro_tenant_responde_404(): void
    {
        [$tenantA, $userA] = $this->tenantConUsuario();
        [, $userB] = $this->tenantConUsuario();

        $ajena = $this->conversacionDe($tenantA, $userA);

        $this->loginAs($userB);

        $this->getJson("/asistente/conversaciones/{$ajena->id}")->assertNotFound();
    }

    public function test_borrar_una_conversacion_de_otro_tenant_responde_404_y_no_la_borra(): void
    {
        [$tenantA, $userA] = $this->tenantConUsuario();
        [, $userB] = $this->tenantConUsuario();

        $ajena = $this->conversacionDe($tenantA, $userA);

        $this->loginAs($userB);

        $this->deleteJson("/asistente/conversaciones/{$ajena->id}")->assertNotFound();

        $this->assertDatabaseHas('asistente_conversaciones', ['id' => $ajena->id]);
    }

    // --- Entre usuarios del mismo tenant ------------------------------------

    public function test_cada_persona_ve_solo_sus_propias_conversaciones(): void
    {
        [$tenant, $ana] = $this->tenantConUsuario();
        $luis = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $this->conversacionDe($tenant, $ana, 'Hilo de Ana');
        $this->conversacionDe($tenant, $luis, 'Hilo de Luis');

        $this->loginAs($luis);

        $titulos = collect($this->getJson('/asistente/conversaciones')->json('conversaciones'))->pluck('titulo');

        $this->assertContains('Hilo de Luis', $titulos);
        $this->assertNotContains('Hilo de Ana', $titulos);
    }

    public function test_abrir_la_conversacion_de_otra_persona_del_mismo_tenant_responde_404_no_403(): void
    {
        [$tenant, $ana] = $this->tenantConUsuario();
        $luis = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $deAna = $this->conversacionDe($tenant, $ana);

        $this->loginAs($luis);

        // 404, nunca 403: un 403 confirmaría que ese hilo existe.
        $this->getJson("/asistente/conversaciones/{$deAna->id}")->assertNotFound();
    }

    public function test_borrar_la_conversacion_de_otra_persona_del_mismo_tenant_responde_404(): void
    {
        [$tenant, $ana] = $this->tenantConUsuario();
        $luis = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $deAna = $this->conversacionDe($tenant, $ana);

        $this->loginAs($luis);

        $this->deleteJson("/asistente/conversaciones/{$deAna->id}")->assertNotFound();

        $this->assertDatabaseHas('asistente_conversaciones', ['id' => $deAna->id]);
    }

    // --- Los mensajes tampoco se filtran ------------------------------------

    public function test_los_mensajes_llevan_tenant_id_y_quedan_fuera_del_scope_ajeno(): void
    {
        [$tenantA, $userA] = $this->tenantConUsuario();
        [$tenantB] = $this->tenantConUsuario();

        $conversacion = $this->conversacionDe($tenantA, $userA);
        $conversacion->mensajes()->create([
            'tenant_id' => $tenantA->id,
            'rol' => 'user',
            'contenido' => 'Dato confidencial de A',
        ]);

        tenancy()->initialize($tenantB);

        $this->assertSame(0, AsistenteMensaje::query()->count());
    }
}
