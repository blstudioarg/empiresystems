<?php

namespace Tests\Unit;

use App\Models\Articulo;
use App\Models\Tenant;
use App\Support\EmparejadorArticulo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cascada de research D9: SKU exacto → nombre ≥88 % único → línea libre.
 *
 * El umbral es más alto que el de proveedor (85 %) a propósito: un falso positivo aquí mete stock
 * en el artículo equivocado, que es un error caro y silencioso.
 */
class EmparejadorArticuloTest extends TestCase
{
    use RefreshDatabase;

    private function emparejar(array $linea): array
    {
        return (new EmparejadorArticulo)->emparejar($linea);
    }

    private function tenantActivo(): Tenant
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        return $tenant;
    }

    public function test_empareja_por_sku_exacto(): void
    {
        $tenant = $this->tenantActivo();
        $articulo = Articulo::factory()->create(['tenant_id' => $tenant->id, 'sku' => 'T-M6-20', 'nombre' => 'Tornillo M6 20mm']);

        $resultado = $this->emparejar(['referencia' => 'T-M6-20', 'concepto' => 'Cualquier cosa']);

        $this->assertSame('referencia', $resultado['criterio']);
        $this->assertSame($articulo->id, $resultado['articulo_id']);
    }

    public function test_el_sku_se_normaliza_antes_de_comparar(): void
    {
        $tenant = $this->tenantActivo();
        $articulo = Articulo::factory()->create(['tenant_id' => $tenant->id, 'sku' => 'T-M6-20']);

        $resultado = $this->emparejar(['referencia' => ' t m6 20 ', 'concepto' => 'x']);

        $this->assertSame('referencia', $resultado['criterio']);
        $this->assertSame($articulo->id, $resultado['articulo_id']);
    }

    public function test_sin_sku_empareja_por_nombre_muy_parecido_y_unico(): void
    {
        $tenant = $this->tenantActivo();
        $articulo = Articulo::factory()->create(['tenant_id' => $tenant->id, 'sku' => 'X1', 'nombre' => 'Tornillo M6 20mm']);

        $resultado = $this->emparejar(['referencia' => null, 'concepto' => 'Tornillo M6 20mm']);

        $this->assertSame('nombre', $resultado['criterio']);
        $this->assertSame($articulo->id, $resultado['articulo_id']);
        $this->assertGreaterThanOrEqual(88, $resultado['similitud']);
    }

    public function test_sin_coincidencia_devuelve_linea_libre_que_es_un_resultado_valido(): void
    {
        $tenant = $this->tenantActivo();
        Articulo::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Tornillo M6 20mm']);

        $resultado = $this->emparejar(['referencia' => null, 'concepto' => 'Portes de la mercancía']);

        $this->assertSame('sin_coincidencia', $resultado['criterio']);
        $this->assertNull($resultado['articulo_id']);
        $this->assertFalse($resultado['mueve_stock']);
    }

    public function test_dos_candidatos_por_encima_del_umbral_no_eligen_ninguno(): void
    {
        $tenant = $this->tenantActivo();
        Articulo::factory()->create(['tenant_id' => $tenant->id, 'sku' => 'A1', 'nombre' => 'Tornillo M6 20mm']);
        Articulo::factory()->create(['tenant_id' => $tenant->id, 'sku' => 'A2', 'nombre' => 'Tornillo M6 20mm']);

        $resultado = $this->emparejar(['referencia' => null, 'concepto' => 'Tornillo M6 20mm']);

        $this->assertSame('sin_coincidencia', $resultado['criterio']);
    }

    public function test_solo_considera_articulos_activos_del_tenant(): void
    {
        $tenant = $this->tenantActivo();
        Articulo::factory()->create(['tenant_id' => $tenant->id, 'sku' => 'VIEJO', 'nombre' => 'Tornillo M6 20mm', 'activo' => false]);

        $resultado = $this->emparejar(['referencia' => 'VIEJO', 'concepto' => 'Tornillo M6 20mm']);

        $this->assertSame('sin_coincidencia', $resultado['criterio']);
    }

    public function test_no_empareja_con_un_articulo_de_otro_tenant(): void
    {
        $otro = Tenant::factory()->create();
        Articulo::factory()->create(['tenant_id' => $otro->id, 'sku' => 'AJENO', 'nombre' => 'Tornillo ajeno']);

        $this->tenantActivo();

        $this->assertSame('sin_coincidencia', $this->emparejar(['referencia' => 'AJENO', 'concepto' => 'x'])['criterio']);
    }

    public function test_mueve_stock_solo_para_producto_con_gestion_de_stock(): void
    {
        $tenant = $this->tenantActivo();

        $conStock = Articulo::factory()->producto()->create([
            'tenant_id' => $tenant->id, 'sku' => 'CON', 'gestion_stock' => true,
        ]);
        $sinStock = Articulo::factory()->producto()->create([
            'tenant_id' => $tenant->id, 'sku' => 'SIN', 'gestion_stock' => false,
        ]);

        $this->assertTrue($this->emparejar(['referencia' => 'CON', 'concepto' => 'x'])['mueve_stock']);
        $this->assertFalse($this->emparejar(['referencia' => 'SIN', 'concepto' => 'x'])['mueve_stock']);
        $this->assertSame($conStock->id, $this->emparejar(['referencia' => 'CON', 'concepto' => 'x'])['articulo_id']);
        $this->assertSame($sinStock->id, $this->emparejar(['referencia' => 'SIN', 'concepto' => 'x'])['articulo_id']);
    }

    public function test_un_servicio_nunca_mueve_stock(): void
    {
        $tenant = $this->tenantActivo();
        Articulo::factory()->servicio()->create(['tenant_id' => $tenant->id, 'sku' => 'SERV', 'gestion_stock' => false]);

        $this->assertFalse($this->emparejar(['referencia' => 'SERV', 'concepto' => 'x'])['mueve_stock']);
    }
}
