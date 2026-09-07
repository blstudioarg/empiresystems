<?php

namespace Tests\Unit;

use App\Models\Proveedor;
use App\Models\Tenant;
use App\Support\EmparejadorProveedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cascada de research D8: NIF exacto → nombre ≥85 % **único** → sin coincidencia.
 *
 * La regla del candidato único es deliberada: ante dos parecidos, elegir uno al azar sería peor
 * que no elegir, porque el usuario podría no notar el cambiazo.
 */
class EmparejadorProveedorTest extends TestCase
{
    use RefreshDatabase;

    private function emparejar(array $emisor): array
    {
        return (new EmparejadorProveedor)->emparejar($emisor);
    }

    private function tenantActivo(): Tenant
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        return $tenant;
    }

    public function test_empareja_por_nif_exacto(): void
    {
        $tenant = $this->tenantActivo();
        $proveedor = Proveedor::factory()->create(['tenant_id' => $tenant->id, 'nif' => 'B12345678', 'nombre' => 'Suministros SL']);

        $resultado = $this->emparejar(['nif' => 'B12345678', 'nombre' => 'Da igual el nombre']);

        $this->assertSame('nif', $resultado['criterio']);
        $this->assertSame($proveedor->id, $resultado['proveedor_id']);
    }

    public function test_el_nif_se_normaliza_antes_de_comparar(): void
    {
        $tenant = $this->tenantActivo();
        $proveedor = Proveedor::factory()->create(['tenant_id' => $tenant->id, 'nif' => 'B12345678']);

        // Tal como suele venir de un OCR: minúsculas, espacios, guiones y puntos.
        $resultado = $this->emparejar(['nif' => ' b-12.345.678 ', 'nombre' => null]);

        $this->assertSame('nif', $resultado['criterio']);
        $this->assertSame($proveedor->id, $resultado['proveedor_id']);
    }

    public function test_sin_nif_empareja_por_nombre_muy_parecido_si_es_unico(): void
    {
        $tenant = $this->tenantActivo();
        $proveedor = Proveedor::factory()->create(['tenant_id' => $tenant->id, 'nif' => 'B99999999', 'nombre' => 'Suministros Industriales']);

        $resultado = $this->emparejar(['nif' => null, 'nombre' => 'Suministros Industriales S.L.']);

        $this->assertSame('nombre', $resultado['criterio']);
        $this->assertSame($proveedor->id, $resultado['proveedor_id']);
        $this->assertGreaterThanOrEqual(85, $resultado['similitud']);
    }

    public function test_dos_candidatos_por_encima_del_umbral_no_eligen_ninguno(): void
    {
        $tenant = $this->tenantActivo();
        Proveedor::factory()->create(['tenant_id' => $tenant->id, 'nif' => 'B1', 'nombre' => 'Distribuciones Norte']);
        Proveedor::factory()->create(['tenant_id' => $tenant->id, 'nif' => 'B2', 'nombre' => 'Distribuciones Norte']);

        $resultado = $this->emparejar(['nif' => null, 'nombre' => 'Distribuciones Norte']);

        $this->assertSame('sin_coincidencia', $resultado['criterio'], 'Ante un empate no se elige arbitrariamente.');
        $this->assertNull($resultado['proveedor_id']);
    }

    public function test_un_nombre_distinto_no_empareja_y_devuelve_los_datos_leidos(): void
    {
        $this->tenantActivo();

        $resultado = $this->emparejar(['nif' => 'B77777777', 'nombre' => 'Ferretería Del Sur', 'ciudad' => 'Cádiz']);

        $this->assertSame('sin_coincidencia', $resultado['criterio']);
        $this->assertNull($resultado['proveedor_id']);
        $this->assertSame('Ferretería Del Sur', $resultado['datos_nuevos']['nombre']);
        $this->assertSame('Cádiz', $resultado['datos_nuevos']['ciudad']);
    }

    public function test_no_empareja_con_un_proveedor_de_otro_tenant(): void
    {
        $otro = Tenant::factory()->create();
        Proveedor::factory()->create(['tenant_id' => $otro->id, 'nif' => 'B12345678', 'nombre' => 'Ajeno SL']);

        $this->tenantActivo();

        $resultado = $this->emparejar(['nif' => 'B12345678', 'nombre' => 'Ajeno SL']);

        $this->assertSame('sin_coincidencia', $resultado['criterio']);
        $this->assertNull($resultado['proveedor_id']);
    }

    public function test_un_emisor_vacio_no_empareja(): void
    {
        $tenant = $this->tenantActivo();
        Proveedor::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Suministros SL']);

        $this->assertSame('sin_coincidencia', $this->emparejar([])['criterio']);
    }

    public function test_compara_tambien_contra_la_razon_social(): void
    {
        $tenant = $this->tenantActivo();
        $proveedor = Proveedor::factory()->create([
            'tenant_id' => $tenant->id,
            'nif' => 'B55555555',
            'nombre' => 'Ferretería Pepe',
            'razon_social' => 'Comercial Pepe Hermanos',
        ]);

        $resultado = $this->emparejar(['nif' => null, 'nombre' => 'Comercial Pepe Hermanos SA']);

        $this->assertSame('nombre', $resultado['criterio']);
        $this->assertSame($proveedor->id, $resultado['proveedor_id']);
    }
}
