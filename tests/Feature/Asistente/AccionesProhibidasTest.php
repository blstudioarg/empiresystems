<?php

namespace Tests\Feature\Asistente;

use App\Enums\EstadoFactura;
use App\Enums\TipoFactura;
use App\Ia\CatalogoTools;
use App\Ia\Tools\CrearFacturaBorrador;
use App\Ia\Tools\EditarFacturaBorrador;
use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\Serie;
use App\Models\Tenant;
use App\Services\RegistroFacturaBorrador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Bloqueo estructural (FR-006, SC-002, contrato #3/#4/#5): las acciones prohibidas no existen como
 * tools; los importes salen del servidor; las facturas se crean en borrador.
 */
class AccionesProhibidasTest extends TestCase
{
    use RefreshDatabase;

    private function activar(Tenant $tenant): void
    {
        tenancy()->initialize($tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getTenantKey());
    }

    public function test_el_catalogo_no_contiene_tools_prohibidas(): void
    {
        $nombres = array_map(fn ($t) => $t->nombre(), CatalogoTools::todas());

        foreach (['emitir', 'anular', 'pagar', 'pago', 'borrar', 'eliminar', 'usuario', 'rol', 'config'] as $prohibido) {
            foreach ($nombres as $nombre) {
                $this->assertStringNotContainsString($prohibido, $nombre, "No debe existir una tool «{$nombre}» con «{$prohibido}».");
            }
        }
    }

    public function test_las_tools_de_factura_no_aceptan_importes_en_su_schema(): void
    {
        foreach ([new CrearFacturaBorrador, new EditarFacturaBorrador] as $tool) {
            $json = json_encode($tool->schema());
            $this->assertStringNotContainsString('precio', $json);
            $this->assertStringNotContainsString('total', $json);
            $this->assertStringNotContainsString('importe', $json);
        }
    }

    public function test_la_factura_creada_queda_en_borrador_con_total_del_servidor(): void
    {
        $tenant = Tenant::factory()->create();
        $this->activar($tenant);
        Serie::factory()->create(['tenant_id' => $tenant->id, 'tipo' => TipoFactura::Ordinaria, 'activa' => true]);

        $cliente = Cliente::factory()->for($tenant)->create();
        $articulo = Articulo::factory()->for($tenant)->create(['precio' => 100, 'tipo_impositivo' => 21]);

        $factura = app(RegistroFacturaBorrador::class)->crear($cliente, [[
            'articulo_id' => $articulo->id,
            'concepto' => $articulo->nombre,
            'unidad' => null,
            'cantidad' => 2,
            'precio_unitario' => (float) $articulo->precio,
            'tipo_impositivo' => (float) $articulo->tipo_impositivo,
        ]]);

        $this->assertSame(EstadoFactura::Borrador, $factura->estado);
        // 2 × 100 = 200 base + 21% = 242 total (calculado por el servidor).
        $this->assertEquals(242.0, (float) $factura->total);
    }

    public function test_editar_factura_emitida_es_rechazado(): void
    {
        $tenant = Tenant::factory()->create();
        $this->activar($tenant);
        Serie::factory()->create(['tenant_id' => $tenant->id, 'tipo' => TipoFactura::Ordinaria, 'activa' => true]);

        $cliente = Cliente::factory()->for($tenant)->create();
        $articulo = Articulo::factory()->for($tenant)->create(['precio' => 50, 'tipo_impositivo' => 21]);
        $factura = app(RegistroFacturaBorrador::class)->crear($cliente, [[
            'articulo_id' => $articulo->id, 'concepto' => $articulo->nombre, 'unidad' => null,
            'cantidad' => 1, 'precio_unitario' => 50.0, 'tipo_impositivo' => 21.0,
        ]]);
        $factura->update(['estado' => EstadoFactura::Emitida]);

        $this->expectException(ValidationException::class);
        (new EditarFacturaBorrador)->proponer(['id' => $factura->id, 'lineas' => [['articulo_id' => $articulo->id, 'cantidad' => 3]]]);
    }
}
