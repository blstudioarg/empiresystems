<?php

namespace Tests\Feature;

use App\Exceptions\VerifactuNoRegistrableException;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EmisorFacturas;
use App\Services\RegistroVerifactu;
use App\Support\VerifactuTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CadenaVerifactuTest extends TestCase
{
    use RefreshDatabase;

    private function tenantConVerifactu(): Tenant
    {
        $tenant = Tenant::factory()->create(['nif' => 'A58818501']);
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

    public function test_el_primer_eslabon_no_tiene_huella_anterior_y_los_siguientes_encadenan_sin_huecos(): void
    {
        Queue::fake();

        $tenant = $this->tenantConVerifactu();
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);
        $emisor = app(EmisorFacturas::class);

        $huellas = [];
        foreach (range(1, 4) as $i) {
            $factura = $emisor->emitir($this->facturaBorradorValida($tenant, $serie));
            $huellas[] = ['huella' => $factura->huella, 'anterior' => $factura->huella_anterior];
        }

        $this->assertSame('', $huellas[0]['anterior']);

        for ($i = 1; $i < count($huellas); $i++) {
            $this->assertSame($huellas[$i - 1]['huella'], $huellas[$i]['anterior']);
        }

        $this->assertCount(4, array_unique(array_column($huellas, 'huella')));
    }

    public function test_emisiones_secuenciales_no_bifurcan_ni_repiten_huella_anterior(): void
    {
        // Simula concurrencia igual que MovimientoStockConcurrenciaTest: ejecuta varias emisiones
        // seguidas, cada una con su propia transacción con lockForUpdate() (research R2), y
        // verifica que la cadena resultante no tiene bifurcaciones ni huellas repetidas.
        Queue::fake();

        $tenant = $this->tenantConVerifactu();
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);
        $emisor = app(EmisorFacturas::class);

        $huellasAnteriores = [];
        for ($i = 0; $i < 10; $i++) {
            $factura = $emisor->emitir($this->facturaBorradorValida($tenant, $serie));
            $huellasAnteriores[] = $factura->huella_anterior;
        }

        $this->assertCount(10, array_unique($huellasAnteriores), 'Ninguna huella_anterior debe repetirse (sin bifurcaciones).');
    }

    public function test_una_factura_ya_registrada_no_se_puede_volver_a_sellar(): void
    {
        Queue::fake();

        $tenant = $this->tenantConVerifactu();
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);
        $emisor = app(EmisorFacturas::class);

        $factura = $emisor->emitir($this->facturaBorradorValida($tenant, $serie));
        $huellaOriginal = $factura->huella;

        $this->expectException(VerifactuNoRegistrableException::class);

        app(RegistroVerifactu::class)->registrar($factura);

        $this->assertSame($huellaOriginal, $factura->refresh()->huella);
    }

    public function test_emitir_nuevas_facturas_no_altera_los_eslabones_previos_de_la_cadena(): void
    {
        Queue::fake();

        $tenant = $this->tenantConVerifactu();
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);
        $emisor = app(EmisorFacturas::class);

        $primera = $emisor->emitir($this->facturaBorradorValida($tenant, $serie));
        $huellaOriginal = $primera->huella;

        $emisor->emitir($this->facturaBorradorValida($tenant, $serie));
        $emisor->emitir($this->facturaBorradorValida($tenant, $serie));

        $this->assertSame($huellaOriginal, $primera->refresh()->huella);
        $this->assertSame('', $primera->refresh()->huella_anterior);
    }

    public function test_una_factura_con_registro_verifactu_no_se_puede_editar_ni_borrar(): void
    {
        Queue::fake();

        $tenant = $this->tenantConVerifactu();
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $factura = app(EmisorFacturas::class)->emitir($this->facturaBorradorValida($tenant, $serie));
        $huellaOriginal = $factura->huella;

        $this->loginAs($user);

        $this->put("/facturas/{$factura->id}", [
            'cliente_id' => $factura->cliente_id,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'notas' => 'intento de edición',
            'lineas' => [
                ['concepto' => 'Línea', 'cantidad' => 1, 'precio_unitario' => 100, 'tipo_impositivo' => 21],
            ],
        ])->assertForbidden();
        $this->delete("/facturas/{$factura->id}")->assertForbidden();

        $factura->refresh();
        $this->assertSame($huellaOriginal, $factura->huella);
        $this->assertNotSoftDeleted($factura);
    }
}
