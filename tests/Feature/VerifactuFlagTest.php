<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Serie;
use App\Models\Tenant;
use App\Services\EmisorFacturas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifactuFlagTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_con_el_flag_apagado_emitir_no_genera_huella_ni_qr_ni_evento_verifactu(): void
    {
        // El tenant NUNCA activó Verifactu (default `false`, FR-000); el NIF ni siquiera es
        // válido, para demostrar que RegistroVerifactu no llega a invocarse en absoluto.
        $tenant = Tenant::factory()->create(['nif' => 'INVALIDO']);
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);
        $factura = $this->facturaBorradorValida($tenant, $serie);

        $emitida = app(EmisorFacturas::class)->emitir($factura);

        $this->assertSame('emitida', $emitida->estado->value);
        $this->assertNull($emitida->huella);
        $this->assertNull($emitida->huella_anterior);
        $this->assertNull($emitida->qr_contenido);
        $this->assertNull($emitida->registro_xml);
        $this->assertNull($emitida->registrada_at);
        $this->assertNull($emitida->verifactu_entorno);

        $this->assertDatabaseMissing('factura_eventos', [
            'factura_id' => $emitida->id,
            'tipo_evento' => 'verifactu_alta',
        ]);
    }
}
