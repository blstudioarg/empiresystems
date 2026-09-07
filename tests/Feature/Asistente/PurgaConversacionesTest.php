<?php

namespace Tests\Feature\Asistente;

use App\Models\AsistenteConversacion;
use App\Models\Configuracion;
use App\Models\Tenant;
use App\Models\User;
use App\Support\RetencionAsistenteTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Retención de las conversaciones del asistente (feature 045, FR-016 a FR-019).
 *
 * Esta feature invierte una decisión previa: la conversación era efímera "por diseño, sin retención
 * adicional". Al persistirla pasa a ser dato personal conservado, y el Principio II exige plazo y
 * purga. Sin estos tests en verde el MVP no es desplegable.
 */
class PurgaConversacionesTest extends TestCase
{
    use RefreshDatabase;

    private function conversacion(Tenant $tenant, User $user, int $diasSinActividad): AsistenteConversacion
    {
        $conversacion = AsistenteConversacion::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'titulo' => "Hilo de hace {$diasSinActividad} días",
            'ultima_actividad_en' => now()->subDays($diasSinActividad),
        ]);

        $conversacion->mensajes()->create([
            'tenant_id' => $tenant->id,
            'rol' => 'user',
            'contenido' => 'contenido con datos personales',
        ]);

        return $conversacion;
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantConUsuario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        return [$tenant, $user];
    }

    public function test_purga_las_conversaciones_que_superan_el_plazo_por_defecto(): void
    {
        [$tenant, $user] = $this->tenantConUsuario();

        $vieja = $this->conversacion($tenant, $user, 120);
        $reciente = $this->conversacion($tenant, $user, 10);

        $this->artisan('asistente:purgar')->assertSuccessful();

        $this->assertDatabaseMissing('asistente_conversaciones', ['id' => $vieja->id]);
        $this->assertDatabaseHas('asistente_conversaciones', ['id' => $reciente->id]);
    }

    public function test_la_purga_se_lleva_tambien_los_mensajes(): void
    {
        [$tenant, $user] = $this->tenantConUsuario();

        $vieja = $this->conversacion($tenant, $user, 200);

        $this->artisan('asistente:purgar')->assertSuccessful();

        // Nada de dejar mensajes huérfanos: son justo el dato personal que había que eliminar.
        $this->assertDatabaseMissing('asistente_mensajes', ['conversacion_id' => $vieja->id]);
    }

    public function test_respeta_el_plazo_configurado_por_cada_tenant(): void
    {
        [$tenantCorto, $userCorto] = $this->tenantConUsuario();
        [$tenantLargo, $userLargo] = $this->tenantConUsuario();

        Configuracion::withoutGlobalScopes()->create([
            'tenant_id' => $tenantCorto->id,
            'clave' => RetencionAsistenteTenant::CLAVE_RETENCION_DIAS,
            'valor' => '5',
            'tipo' => 'integer',
            'grupo' => RetencionAsistenteTenant::GRUPO,
        ]);

        $deCorto = $this->conversacion($tenantCorto, $userCorto, 10);
        $deLargo = $this->conversacion($tenantLargo, $userLargo, 10);

        $this->artisan('asistente:purgar')->assertSuccessful();

        // 10 días supera el plazo de 5 del primero, pero no los 90 por defecto del segundo.
        $this->assertDatabaseMissing('asistente_conversaciones', ['id' => $deCorto->id]);
        $this->assertDatabaseHas('asistente_conversaciones', ['id' => $deLargo->id]);
    }

    public function test_una_conversacion_vieja_pero_usada_ayer_no_se_purga(): void
    {
        [$tenant, $user] = $this->tenantConUsuario();

        // Empezada hace un año, pero con actividad ayer: está viva.
        $viva = AsistenteConversacion::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'titulo' => 'Hilo antiguo pero activo',
            'ultima_actividad_en' => now()->subDay(),
            'created_at' => now()->subYear(),
        ]);

        $this->artisan('asistente:purgar')->assertSuccessful();

        $this->assertDatabaseHas('asistente_conversaciones', ['id' => $viva->id]);
    }

    public function test_la_purga_de_un_tenant_no_toca_las_conversaciones_de_otro(): void
    {
        [$tenantA, $userA] = $this->tenantConUsuario();
        [$tenantB, $userB] = $this->tenantConUsuario();

        Configuracion::withoutGlobalScopes()->create([
            'tenant_id' => $tenantA->id,
            'clave' => RetencionAsistenteTenant::CLAVE_RETENCION_DIAS,
            'valor' => '1',
            'tipo' => 'integer',
            'grupo' => RetencionAsistenteTenant::GRUPO,
        ]);

        $deA = $this->conversacion($tenantA, $userA, 30);
        $deB = $this->conversacion($tenantB, $userB, 30);

        $this->artisan('asistente:purgar')->assertSuccessful();

        $this->assertDatabaseMissing('asistente_conversaciones', ['id' => $deA->id]);
        $this->assertDatabaseHas('asistente_conversaciones', ['id' => $deB->id]);
    }
}
