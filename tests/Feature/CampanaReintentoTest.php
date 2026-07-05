<?php

namespace Tests\Feature;

use App\Models\Campana;
use App\Models\CampanaDestinatario;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampanaReintentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_reintentar_repone_solo_fallidos_con_email_y_no_toca_enviados(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $campana = Campana::factory()->create(['tenant_id' => $tenant->id]);

        $enviado = CampanaDestinatario::factory()->enviado()->create([
            'tenant_id' => $tenant->id,
            'campana_id' => $campana->id,
            'cliente_id' => Cliente::factory()->create(['tenant_id' => $tenant->id])->id,
            'email' => 'ok@destino.test',
        ]);
        $fallidoConEmail = CampanaDestinatario::factory()->fallido()->create([
            'tenant_id' => $tenant->id,
            'campana_id' => $campana->id,
            'cliente_id' => Cliente::factory()->create(['tenant_id' => $tenant->id])->id,
            'email' => 'ko@destino.test',
        ]);
        $fallidoSinEmail = CampanaDestinatario::factory()->fallido()->create([
            'tenant_id' => $tenant->id,
            'campana_id' => $campana->id,
            'cliente_id' => Cliente::factory()->create(['tenant_id' => $tenant->id, 'email' => null])->id,
            'email' => null,
        ]);

        $this->loginAs($user);

        $response = $this->postJson("/campanas/{$campana->id}/reintentar");

        $response->assertOk();
        $response->assertJsonFragment(['destinatario_ids' => [$fallidoConEmail->id]]);

        $this->assertEquals('enviado', $enviado->fresh()->estado->value);
        $this->assertEquals('pendiente', $fallidoConEmail->fresh()->estado->value);
        $this->assertNull($fallidoConEmail->fresh()->error);
        $this->assertEquals('fallido', $fallidoSinEmail->fresh()->estado->value);
    }
}
