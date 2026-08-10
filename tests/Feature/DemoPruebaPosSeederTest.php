<?php

namespace Tests\Feature;

use App\Enums\EstadoFactura;
use App\Enums\TipoFactura;
use App\Models\Albaran;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Lead;
use App\Models\MovimientoStock;
use App\Models\Presupuesto;
use App\Models\Tenant;
use Database\Seeders\DemoPruebaPosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garantías del seeder de datos de demo: crea documentos utilizables, no destruye nada de lo
 * preexistente y es idempotente (correrlo dos veces no duplica).
 */
class DemoPruebaPosSeederTest extends TestCase
{
    use RefreshDatabase;

    private function tenantConDominioPruebapos(): Tenant
    {
        $tenant = Tenant::factory()->create(['nombre_comercial' => 'Prueba POS']);
        $tenant->domains()->create(['domain' => 'pruebapos.gestionley.com']);

        return $tenant;
    }

    public function test_crea_documentos_de_demo_en_el_tenant_del_dominio_pruebapos(): void
    {
        $tenant = $this->tenantConDominioPruebapos();

        $this->seed(DemoPruebaPosSeeder::class);

        tenancy()->initialize($tenant);

        $this->assertSame(6, Lead::count());
        $this->assertSame(4, Presupuesto::count());
        $this->assertSame(3, Albaran::count());

        // Facturas ordinarias: todas en borrador (borrables desde la UI).
        $ordinarias = Factura::where('tipo', TipoFactura::Ordinaria)->get();
        $this->assertCount(4, $ordinarias);
        $this->assertTrue($ordinarias->every(fn (Factura $f) => $f->estado === EstadoFactura::Borrador));

        // Tickets simplificados: emitidos, con número asignado.
        $tickets = Factura::where('tipo', TipoFactura::Simplificada)->get();
        $this->assertCount(3, $tickets);
        $this->assertTrue($tickets->every(fn (Factura $f) => $f->estado === EstadoFactura::Emitida));
        $this->assertTrue($tickets->every(fn (Factura $f) => $f->numero !== null));

        // Todo lleva la marca DEMO-SEED para poder identificarlo después.
        $this->assertTrue(Factura::all()->every(fn (Factura $f) => str_starts_with((string) $f->notas, 'DEMO-SEED:')));

        // Movimientos de stock: solo los que genera el flujo (1 albarán entregado + 3 tickets),
        // ninguno creado a mano.
        $this->assertGreaterThan(0, MovimientoStock::count());

        tenancy()->end();
    }

    public function test_no_toca_los_registros_preexistentes(): void
    {
        $tenant = $this->tenantConDominioPruebapos();

        tenancy()->initialize($tenant);
        $clienteReal = Cliente::create([
            'tipo' => 'empresa',
            'nombre' => 'Cliente Real',
            'razon_social' => 'Cliente Real SL',
            'nif' => 'B99999999',
            'pais' => 'ES',
            'notas' => 'no tocar',
        ]);
        tenancy()->end();

        $this->seed(DemoPruebaPosSeeder::class);

        $clienteReal->refresh();
        $this->assertSame('Cliente Real', $clienteReal->nombre);
        $this->assertSame('no tocar', $clienteReal->notas);
        $this->assertNull($clienteReal->deleted_at);
    }

    public function test_es_idempotente_al_correrlo_dos_veces(): void
    {
        $this->tenantConDominioPruebapos();

        $this->seed(DemoPruebaPosSeeder::class);

        $conteos = [
            'leads' => Lead::withoutGlobalScopes()->count(),
            'presupuestos' => Presupuesto::withoutGlobalScopes()->count(),
            'albaranes' => Albaran::withoutGlobalScopes()->count(),
            'facturas' => Factura::withoutGlobalScopes()->count(),
            'movimientos' => MovimientoStock::withoutGlobalScopes()->count(),
        ];

        $this->seed(DemoPruebaPosSeeder::class);

        $this->assertSame($conteos['leads'], Lead::withoutGlobalScopes()->count());
        $this->assertSame($conteos['presupuestos'], Presupuesto::withoutGlobalScopes()->count());
        $this->assertSame($conteos['albaranes'], Albaran::withoutGlobalScopes()->count());
        $this->assertSame($conteos['facturas'], Factura::withoutGlobalScopes()->count());
        $this->assertSame($conteos['movimientos'], MovimientoStock::withoutGlobalScopes()->count());
    }

    public function test_no_hace_nada_si_no_existe_el_tenant(): void
    {
        $this->seed(DemoPruebaPosSeeder::class);

        $this->assertSame(0, Lead::withoutGlobalScopes()->count());
        $this->assertSame(0, Factura::withoutGlobalScopes()->count());
    }
}
