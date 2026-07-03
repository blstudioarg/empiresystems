<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EmisorFacturas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacturaNumeracionTest extends TestCase
{
    use RefreshDatabase;

    private function facturaBorradorValida(Tenant $tenant, Serie $serie, array $overrides = []): Factura
    {
        $cliente = Cliente::factory()->create([
            'tenant_id' => $tenant->id,
            'nif' => '12345678Z',
            'nombre' => 'Cliente de prueba',
            'direccion' => 'Calle Falsa 123',
        ]);

        return Factura::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'serie_id' => $serie->id,
            'cliente_id' => $cliente->id,
            'cliente_nombre' => $cliente->nombre,
            'cliente_nif' => $cliente->nif,
            'cliente_direccion' => $cliente->direccion,
            'estado' => 'borrador',
            'numero' => null,
            'numero_completo' => null,
            'base_total' => 100,
            'total' => 121,
        ], $overrides));
    }

    public function test_dos_emisiones_de_la_misma_serie_reciben_numeros_consecutivos_sin_huecos(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);

        $facturaUno = $this->facturaBorradorValida($tenant, $serie);
        $facturaDos = $this->facturaBorradorValida($tenant, $serie);

        $this->loginAs($user);

        $this->post("/facturas/{$facturaUno->id}/emitir");
        $this->post("/facturas/{$facturaDos->id}/emitir");

        $this->assertEquals(1, $facturaUno->refresh()->numero);
        $this->assertEquals(2, $facturaDos->refresh()->numero);
    }

    public function test_el_contador_se_reinicia_a_1_en_un_nuevo_anio_natural(): void
    {
        $tenant = Tenant::factory()->create();
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        // Factura ya emitida el año anterior en esta serie.
        Factura::factory()->create([
            'tenant_id' => $tenant->id,
            'serie_id' => $serie->id,
            'cliente_id' => $cliente->id,
            'estado' => 'emitida',
            'numero' => 5,
            'numero_completo' => 'F-'.(now()->year - 1).'-0005',
            'fecha_expedicion' => now()->subYear()->toDateString(),
        ]);

        $facturaEsteAnio = $this->facturaBorradorValida($tenant, $serie);

        $emisor = app(EmisorFacturas::class);
        $emisor->emitir($facturaEsteAnio);

        $this->assertEquals(1, $facturaEsteAnio->refresh()->numero);
    }

    public function test_emisiones_seguidas_de_la_misma_serie_nunca_repiten_ni_saltan_numero(): void
    {
        $tenant = Tenant::factory()->create();
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);

        $emisor = app(EmisorFacturas::class);

        $numeros = [];
        foreach (range(1, 3) as $i) {
            $factura = $this->facturaBorradorValida($tenant, $serie);
            $emisor->emitir($factura);
            $numeros[] = $factura->refresh()->numero;
        }

        $this->assertEquals([1, 2, 3], $numeros);
    }
}
