<?php

namespace Tests\Feature;

use App\Enums\EntornoVerifactu;
use App\Enums\TipoFactura;
use App\Enums\VerifactuEstado;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Serie;
use App\Models\Tenant;
use App\Services\EmisorFacturas;
use App\Services\RegistroTicket;
use App\Support\VerifactuTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RegistroVerifactuTest extends TestCase
{
    use RefreshDatabase;

    private function tenantConVerifactu(array $overrides = []): Tenant
    {
        $tenant = Tenant::factory()->create(array_merge(['nif' => 'A58818501'], $overrides));
        VerifactuTenant::activar($tenant->id, true);

        return $tenant;
    }

    private function facturaBorradorValida(Tenant $tenant, Serie $serie, array $overrides = []): Factura
    {
        $cliente = Cliente::factory()->create([
            'tenant_id' => $tenant->id,
            'nif' => 'B12345674',
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
            'cuota_impuesto_total' => 21,
            'total' => 121,
        ], $overrides));
    }

    public function test_emitir_con_el_flag_activo_sella_el_registro_verifactu(): void
    {
        Queue::fake();

        $tenant = $this->tenantConVerifactu();
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);
        $factura = $this->facturaBorradorValida($tenant, $serie);

        $emitida = app(EmisorFacturas::class)->emitir($factura);

        $this->assertNotEmpty($emitida->huella);
        $this->assertSame('', $emitida->huella_anterior);
        $this->assertNotEmpty($emitida->registro_xml);
        $this->assertSame(VerifactuEstado::Registrada, $emitida->verifactu_estado);
        $this->assertSame(EntornoVerifactu::Pruebas, $emitida->verifactu_entorno);
        $this->assertNotNull($emitida->registrada_at);
        $this->assertNotEmpty($emitida->qr_contenido);

        $this->assertDatabaseHas('factura_eventos', [
            'tenant_id' => $tenant->id,
            'factura_id' => $emitida->id,
            'tipo_evento' => 'verifactu_alta',
            'huella' => $emitida->huella,
        ]);
    }

    public function test_si_el_sellado_falla_la_emision_se_revierte_por_completo(): void
    {
        // NIF de emisor inválido: RegistroVerifactu lanza VerifactuNoRegistrableException dentro
        // de la transacción de emitir(); no debe quedar ni número asignado ni factura emitida.
        $tenant = $this->tenantConVerifactu(['nif' => 'B00000001']);
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);
        $factura = $this->facturaBorradorValida($tenant, $serie);

        try {
            app(EmisorFacturas::class)->emitir($factura);
            $this->fail('Se esperaba que la emisión lanzara una excepción.');
        } catch (\DomainException) {
            // esperado
        }

        $factura->refresh();
        $this->assertSame('borrador', $factura->estado->value);
        $this->assertNull($factura->numero);
        $this->assertNull($factura->huella);
        $this->assertDatabaseMissing('factura_eventos', ['factura_id' => $factura->id]);
    }

    public function test_los_tres_tipos_de_documento_encadenan_en_la_misma_cadena_del_tenant(): void
    {
        Queue::fake();

        $tenant = $this->tenantConVerifactu();
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);
        $serieSimplificada = Serie::factory()->create(['tenant_id' => $tenant->id, 'codigo' => 'S']);

        $ordinaria = app(EmisorFacturas::class)->emitir(
            $this->facturaBorradorValida($tenant, $serie, ['tipo' => TipoFactura::Ordinaria])
        );

        $simplificada = app(EmisorFacturas::class)->emitir(
            $this->facturaBorradorValida($tenant, $serieSimplificada, ['tipo' => TipoFactura::Simplificada, 'cliente_nif' => null, 'cliente_nombre' => null, 'cliente_direccion' => null])
        );

        $rectificativa = app(EmisorFacturas::class)->emitir(
            $this->facturaBorradorValida($tenant, $serie, [
                'tipo' => TipoFactura::Rectificativa,
                'es_rectificativa' => true,
                'factura_rectificada_id' => $ordinaria->id,
                'motivo_rectificacion' => 'Error en el importe',
                'tipo_rectificacion' => 'diferencias',
            ])
        );

        $this->assertSame('', $ordinaria->huella_anterior);
        $this->assertSame($ordinaria->huella, $simplificada->huella_anterior);
        $this->assertSame($simplificada->huella, $rectificativa->huella_anterior);
    }

    public function test_bloquea_el_registro_si_el_nif_del_emisor_no_es_valido(): void
    {
        $tenant = $this->tenantConVerifactu(['nif' => 'B00000001']);
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);
        $factura = $this->facturaBorradorValida($tenant, $serie);

        $this->expectException(\App\Exceptions\VerifactuNoRegistrableException::class);

        app(EmisorFacturas::class)->emitir($factura);
    }

    public function test_lanza_cadena_rota_si_el_entorno_configurado_no_coincide_con_la_cadena_existente(): void
    {
        Queue::fake();

        $tenant = $this->tenantConVerifactu();
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);

        app(EmisorFacturas::class)->emitir($this->facturaBorradorValida($tenant, $serie));

        VerifactuTenant::establecerEntorno($tenant->id, EntornoVerifactu::Produccion);

        $this->expectException(\App\Exceptions\CadenaVerifactuRotaException::class);

        app(EmisorFacturas::class)->emitir($this->facturaBorradorValida($tenant, $serie));
    }

    /**
     * El ticket del POS pasa por `RegistroTicket::registrar()`, que delega en
     * `EmisorFacturas::emitir()` para la emisión: debe heredar el sellado sin ninguna lógica
     * propia (Principio III, un único lugar de cálculo) — T027.
     */
    public function test_el_flujo_pos_hereda_el_sellado_verifactu_a_traves_de_emisor_facturas(): void
    {
        Queue::fake();

        $tenant = $this->tenantConVerifactu();
        Serie::factory()->simplificada()->create(['tenant_id' => $tenant->id]);

        tenancy()->initialize($tenant);

        $ticket = app(RegistroTicket::class)->registrar([
            'lineas' => [
                ['concepto' => 'Café', 'cantidad' => 1, 'precio_unitario' => 2, 'tipo_impositivo' => 10],
            ],
        ]);

        tenancy()->end();

        $this->assertNotEmpty($ticket->huella);
        $this->assertSame(VerifactuEstado::Registrada, $ticket->verifactu_estado);
    }
}
