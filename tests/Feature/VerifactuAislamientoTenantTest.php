<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Serie;
use App\Models\Tenant;
use App\Services\EmisorFacturas;
use App\Support\VerifactuTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Principio I (NON-NEGOTIABLE): con ≥2 tenants, ninguna huella de uno aparece como huella_anterior
 * del otro. Las cadenas Verifactu son totalmente independientes por tenant (FR-017).
 */
class VerifactuAislamientoTenantTest extends TestCase
{
    use RefreshDatabase;

    private function tenantConVerifactu(string $nif): Tenant
    {
        $tenant = Tenant::factory()->create(['nif' => $nif]);
        VerifactuTenant::activar($tenant->id, true);

        return $tenant;
    }

    private function facturaBorradorValida(Tenant $tenant, Serie $serie): Factura
    {
        $cliente = Cliente::factory()->create([
            'tenant_id' => $tenant->id,
            'nif' => 'B12345674',
            'nombre' => 'Cliente de prueba',
            'direccion' => 'Calle Falsa 123',
        ]);

        return Factura::factory()->create([
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
            'cuota_impuesto_total' => 21,
            'total' => 121,
        ]);
    }

    public function test_las_cadenas_de_dos_tenants_no_se_cruzan(): void
    {
        Queue::fake();

        $tenantA = $this->tenantConVerifactu('A58818501');
        $tenantB = $this->tenantConVerifactu('B12345674');

        $serieA = Serie::factory()->create(['tenant_id' => $tenantA->id]);
        $serieB = Serie::factory()->create(['tenant_id' => $tenantB->id]);

        $emisor = app(EmisorFacturas::class);

        $huellasA = [];
        $huellasB = [];

        foreach (range(1, 3) as $i) {
            $huellasA[] = $emisor->emitir($this->facturaBorradorValida($tenantA, $serieA))->huella;
            $huellasB[] = $emisor->emitir($this->facturaBorradorValida($tenantB, $serieB))->huella;
        }

        $this->assertEmpty(array_intersect($huellasA, $huellasB), 'Ningún tenant debe compartir huellas con otro.');

        // El primer eslabón de cada tenant no tiene huella_anterior, aunque el otro tenant ya
        // hubiera emitido facturas antes en tiempo real.
        $primeraA = Factura::where('tenant_id', $tenantA->id)->whereNotNull('huella')->orderBy('id')->first();
        $primeraB = Factura::where('tenant_id', $tenantB->id)->whereNotNull('huella')->orderBy('id')->first();

        $this->assertSame('', $primeraA->huella_anterior);
        $this->assertSame('', $primeraB->huella_anterior);
    }

    public function test_activar_verifactu_en_un_tenant_no_activa_el_flag_en_otro(): void
    {
        $tenantA = $this->tenantConVerifactu('A58818501');
        $tenantB = Tenant::factory()->create(['nif' => 'B12345674']);

        $this->assertTrue(VerifactuTenant::activo($tenantA->id));
        $this->assertFalse(VerifactuTenant::activo($tenantB->id));
    }
}
