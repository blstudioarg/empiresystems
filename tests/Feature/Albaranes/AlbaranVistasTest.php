<?php

namespace Tests\Feature\Albaranes;

use App\Models\Albaran;
use App\Models\AlbaranLinea;
use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\PresupuestoLinea;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlbaranVistasTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_vistas_de_albaranes_renderizan_sin_errores(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $presupuesto = Presupuesto::factory()->aceptado()->create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id]);
        PresupuestoLinea::factory()->create([
            'tenant_id' => $tenant->id,
            'presupuesto_id' => $presupuesto->id,
            'cantidad' => 10,
            'cantidad_entregada' => 0,
        ]);
        $albaran = Albaran::factory()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'presupuesto_id' => $presupuesto->id,
        ]);
        AlbaranLinea::factory()->create(['tenant_id' => $tenant->id, 'albaran_id' => $albaran->id]);

        $this->loginAs($user);

        $this->get('/albaranes')->assertOk();
        $this->get('/albaranes/crear')->assertOk();
        $this->get("/albaranes/crear?presupuesto_id={$presupuesto->id}")->assertOk();
        $this->get("/albaranes/crear?cliente_id={$cliente->id}")->assertOk();
        $this->get("/albaranes/{$albaran->id}")->assertOk();
        $this->get("/albaranes/{$albaran->id}/editar")->assertOk();

        $this->get('/presupuestos')->assertOk();
        $this->get('/clientes')->assertOk();
    }
}
