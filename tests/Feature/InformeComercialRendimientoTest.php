<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Tenant;
use App\Services\InformeComercial;
use App\Support\AlcanceInformeComercial;
use App\Support\CatalogoPermisos;
use App\Support\FiltrosInforme;
use App\Support\RangoFechas;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * SC-007: el informe responde en <3s con 5.000 leads, 1.000 oportunidades y 1.000 presupuestos en
 * el periodo. Sembrado por inserción masiva (no factories una a una) para que el tiempo medido sea
 * el del propio informe, no el de la siembra de datos de prueba.
 */
class InformeComercialRendimientoTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    public function test_el_informe_responde_en_menos_de_3_segundos_a_volumen_sc007(): void
    {
        $tenant = Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $this->sembrarPermisos();
        $rol = $this->crearRol($tenant, 'Administrador', CatalogoPermisos::claves());
        $usuario = $this->usuarioConRol($tenant, $rol);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getTenantKey());

        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        $ahora = now();
        $fecha = '2026-06-15';

        $this->insertarMasivo('leads', 5000, fn (int $i) => [
            'tenant_id' => $tenant->id,
            'nombre' => "Lead {$i}",
            'email' => "lead{$i}@example.com",
            'estado' => 'nuevo',
            'origen' => 'manual',
            'created_at' => $fecha,
            'updated_at' => $ahora,
        ]);

        $this->insertarMasivo('oportunidades', 1000, fn (int $i) => [
            'tenant_id' => $tenant->id,
            'titulo' => "Oportunidad {$i}",
            'cliente_id' => $cliente->id,
            'etapa' => 'nueva',
            'importe_estimado' => 100,
            'created_at' => $fecha,
            'updated_at' => $ahora,
        ]);

        $this->insertarMasivo('presupuestos', 1000, fn (int $i) => [
            'tenant_id' => $tenant->id,
            'numero' => "P-2026-{$i}",
            'cliente_id' => $cliente->id,
            'estado' => 'borrador',
            'receptor_pais' => 'ES',
            'fecha_emision' => $fecha,
            'regimen_impositivo' => 'iva',
            'aplica_recargo' => false,
            'base_total' => 100,
            'cuota_impuesto_total' => 21,
            'cuota_recargo_total' => 0,
            'irpf_cuota' => 0,
            'total' => 121,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        $rango = RangoFechas::personalizado(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'));
        $alcance = AlcanceInformeComercial::paraUsuario($usuario);

        $inicio = microtime(true);
        $datos = (new InformeComercial)->generar($rango, FiltrosInforme::desdePeticion([]), $alcance);
        $duracion = microtime(true) - $inicio;

        $this->assertSame(5000, $datos['indicadores']['leads_captados']);
        $this->assertSame(1000, $datos['indicadores']['oportunidades_creadas']);
        $this->assertSame(1000, $datos['indicadores']['presupuestos_emitidos']);
        $this->assertLessThan(3.0, $duracion, "El informe tardó {$duracion}s, por encima del límite de 3s de SC-007.");

        tenancy()->end();
    }

    /**
     * @param  \Closure(int): array<string, mixed>  $fila
     */
    private function insertarMasivo(string $tabla, int $cantidad, \Closure $fila): void
    {
        $lote = [];

        for ($i = 1; $i <= $cantidad; $i++) {
            $lote[] = $fila($i);

            if (count($lote) === 500) {
                DB::table($tabla)->insert($lote);
                $lote = [];
            }
        }

        if ($lote !== []) {
            DB::table($tabla)->insert($lote);
        }
    }
}
