<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Factura;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TopeSimplificada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTopeSimplificadaTest extends TestCase
{
    use RefreshDatabase;

    private function prepararTenant(bool $sectorAmpliado = false): User
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        Serie::factory()->simplificada()->for($tenant, 'tenant')->create();

        Configuracion::create([
            'tenant_id' => $tenant->id,
            'clave' => TopeSimplificada::CLAVE,
            'valor' => $sectorAmpliado ? '1' : '0',
            'tipo' => 'boolean',
            'grupo' => 'facturacion',
        ]);

        return $user;
    }

    private function ticketPorImporte(float $importe): array
    {
        // tipo_impositivo 0 → el bruto (impuestos incl.) coincide con la base = importe.
        return ['lineas' => [
            ['concepto' => 'Servicio', 'cantidad' => 1, 'precio_unitario' => $importe, 'tipo_impositivo' => 0],
        ]];
    }

    public function test_tope_base_permite_400_y_bloquea_por_encima(): void
    {
        $user = $this->prepararTenant(sectorAmpliado: false);
        $this->loginAs($user);

        $this->postJson('/pos', $this->ticketPorImporte(400))->assertCreated();
        $this->postJson('/pos', $this->ticketPorImporte(400.01))->assertStatus(422);

        // Sólo el ticket de 400 € se creó.
        $this->assertEquals(1, Factura::count());
    }

    public function test_sector_ampliado_permite_3000_y_bloquea_por_encima(): void
    {
        $user = $this->prepararTenant(sectorAmpliado: true);
        $this->loginAs($user);

        $this->postJson('/pos', $this->ticketPorImporte(3000))->assertCreated();
        $this->postJson('/pos', $this->ticketPorImporte(3000.01))->assertStatus(422);

        $this->assertEquals(1, Factura::count());
    }

    public function test_sector_ampliado_permite_importe_intermedio_que_el_base_bloquearia(): void
    {
        $user = $this->prepararTenant(sectorAmpliado: true);
        $this->loginAs($user);

        $this->postJson('/pos', $this->ticketPorImporte(500))->assertCreated();
        $this->assertEquals(1, Factura::count());
    }
}
