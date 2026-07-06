<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Configuracion;
use App\Models\Factura;
use App\Models\Proveedor;
use App\Models\Tenant;
use App\Services\GeneradorFacturae;
use App\Support\RetencionLogsTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FacturaePurgaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documentos');
    }

    public function test_purga_borra_xml_emitido_mas_antiguo_que_la_retencion(): void
    {
        $tenant = Tenant::factory()->create();
        $factura = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'fecha_expedicion' => now()->subDays(RetencionLogsTenant::DEFAULT_RETENCION_DIAS + 1),
        ]);

        $ruta = app(GeneradorFacturae::class)->rutaArchivo($factura);
        Storage::disk('documentos')->put($ruta, '<xml/>');

        $this->artisan('facturae:purgar')->assertExitCode(0);

        $this->assertFalse(Storage::disk('documentos')->exists($ruta));
    }

    public function test_purga_no_borra_xml_emitido_dentro_de_plazo(): void
    {
        $tenant = Tenant::factory()->create();
        $factura = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'fecha_expedicion' => now()->subDays(10),
        ]);

        $ruta = app(GeneradorFacturae::class)->rutaArchivo($factura);
        Storage::disk('documentos')->put($ruta, '<xml/>');

        $this->artisan('facturae:purgar')->assertExitCode(0);

        $this->assertTrue(Storage::disk('documentos')->exists($ruta));
    }

    public function test_purga_borra_xml_recibido_antiguo_y_limpia_la_ruta(): void
    {
        $tenant = Tenant::factory()->create();
        $proveedor = Proveedor::factory()->create(['tenant_id' => $tenant->id]);
        $ruta = "tenants/{$tenant->id}/facturae-recibidas/antiguo.xml";
        Storage::disk('documentos')->put($ruta, '<xml/>');

        $compra = Compra::factory()->facturae()->create([
            'tenant_id' => $tenant->id,
            'proveedor_id' => $proveedor->id,
            'fecha' => now()->subDays(RetencionLogsTenant::DEFAULT_RETENCION_DIAS + 1),
            'archivo_recibido_path' => $ruta,
        ]);

        $this->artisan('facturae:purgar')->assertExitCode(0);

        $this->assertFalse(Storage::disk('documentos')->exists($ruta));
        $this->assertNull($compra->fresh()->archivo_recibido_path);
    }

    public function test_purga_respeta_retencion_configurada_por_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        Configuracion::create([
            'tenant_id' => $tenant->id,
            'clave' => RetencionLogsTenant::CLAVE_RETENCION_DIAS,
            'valor' => '30',
            'tipo' => 'integer',
            'grupo' => 'seguridad',
        ]);

        $factura = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'fecha_expedicion' => now()->subDays(31),
        ]);

        $ruta = app(GeneradorFacturae::class)->rutaArchivo($factura);
        Storage::disk('documentos')->put($ruta, '<xml/>');

        $this->artisan('facturae:purgar')->assertExitCode(0);

        $this->assertFalse(Storage::disk('documentos')->exists($ruta));
    }
}
