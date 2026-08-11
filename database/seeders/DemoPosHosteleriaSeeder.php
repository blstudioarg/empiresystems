<?php

namespace Database\Seeders;

use App\Models\Articulo;
use App\Models\PosMesa;
use App\Models\PosOpcion;
use App\Models\PosOpcionGrupo;
use App\Models\PosZona;
use App\Models\Tenant;
use App\Support\ConfigPos;
use Illuminate\Database\Seeder;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Datos de DEMO del módulo de hostelería (feature 038) para el tenant del subdominio `pruebapos`.
 * Activa el módulo y siembra zonas, mesas y un recetario de opciones de ejemplo, sin tocar nada
 * preexistente — mismo contrato que {@see DemoPruebaPosSeeder}.
 *
 * **Idempotente**: cada zona/mesa/grupo/opción usa `firstOrCreate` sobre su nombre; correrlo dos
 * veces no duplica nada. **Fuerza el `tenant_id` activo**: las factories del proyecto declaran
 * `tenant_id => Tenant::factory()` por defecto, así que crear registros aquí sin fijar el tenant
 * a mano generaría tenants fantasma (memoria `project_factory_tenant_id_pitfall`).
 *
 * No abre cuentas ni emite cobros: solo el catálogo (zonas, mesas, opciones), que es lo que un
 * tenant necesita para poder enseñar la Sala sin partir de cero.
 *
 * USO
 *   php artisan db:seed --class=DemoPosHosteleriaSeeder
 *
 * El tenant se resuelve por su dominio; se puede apuntar a otro con `DEMO_TENANT_DOMINIO=xxx`.
 */
class DemoPosHosteleriaSeeder extends Seeder
{
    private const DOMINIO_POR_DEFECTO = 'pruebapos';

    public function run(): void
    {
        $tenant = $this->resolverTenant();

        if (! $tenant) {
            $this->command?->error('No se encontró el tenant. Define DEMO_TENANT_DOMINIO o revisa la tabla `domains`.');

            return;
        }

        $this->command?->info("Sembrando demo del módulo de hostelería en el tenant #{$tenant->id} ({$tenant->nombre_comercial}).");

        ConfigPos::guardar($tenant->id, [
            'hosteleria_activo' => true,
            'opciones_activo' => true,
            'cobro_dividido_activo' => true,
            'suplemento_zona_activo' => true,
        ]);

        tenancy()->initialize($tenant);

        try {
            $zonas = $this->zonas($tenant);
            $this->mesas($tenant, $zonas);
            $this->opciones($tenant);
        } finally {
            tenancy()->end();
        }

        $this->command?->info('Listo. Zonas, mesas y opciones de demo sembradas (o ya existentes).');
    }

    private function resolverTenant(): ?Tenant
    {
        $buscado = env('DEMO_TENANT_DOMINIO', self::DOMINIO_POR_DEFECTO);

        $dominio = Domain::where('domain', 'like', $buscado.'%')->first();

        /** @var Tenant|null $tenant */
        $tenant = $dominio?->tenant;

        return $tenant;
    }

    /** @return array<string, PosZona> */
    private function zonas(Tenant $tenant): array
    {
        $definiciones = [
            'comedor' => ['nombre' => 'Comedor', 'suplemento_porcentaje' => 0, 'orden' => 0],
            'terraza' => ['nombre' => 'Terraza', 'suplemento_porcentaje' => 10, 'orden' => 1],
            'barra' => ['nombre' => 'Barra', 'suplemento_porcentaje' => 0, 'orden' => 2],
        ];

        $zonas = [];

        foreach ($definiciones as $clave => $datos) {
            $zonas[$clave] = PosZona::firstOrCreate(
                ['tenant_id' => $tenant->id, 'nombre' => $datos['nombre']],
                ['suplemento_porcentaje' => $datos['suplemento_porcentaje'], 'orden' => $datos['orden']],
            );
        }

        return $zonas;
    }

    /** @param  array<string, PosZona>  $zonas */
    private function mesas(Tenant $tenant, array $zonas): void
    {
        $definiciones = [
            ['zona' => 'comedor', 'nombre' => 'Mesa 1', 'orden' => 0],
            ['zona' => 'comedor', 'nombre' => 'Mesa 2', 'orden' => 1],
            ['zona' => 'comedor', 'nombre' => 'Mesa 3', 'orden' => 2],
            ['zona' => 'terraza', 'nombre' => 'Mesa T1', 'orden' => 0],
            ['zona' => 'terraza', 'nombre' => 'Mesa T2', 'orden' => 1],
            ['zona' => 'barra', 'nombre' => 'Barra 1', 'orden' => 0],
        ];

        foreach ($definiciones as $datos) {
            PosMesa::firstOrCreate(
                ['tenant_id' => $tenant->id, 'zona_id' => $zonas[$datos['zona']]->id, 'nombre' => $datos['nombre']],
                ['orden' => $datos['orden']],
            );
        }
    }

    private function opciones(Tenant $tenant): void
    {
        $puntoCoccion = PosOpcionGrupo::firstOrCreate(
            ['tenant_id' => $tenant->id, 'nombre' => 'Punto de cocción'],
            ['min_selecciones' => 1, 'max_selecciones' => 1, 'obligatorio' => true, 'orden' => 0],
        );

        foreach (['Poco hecho', 'Al punto', 'Muy hecho'] as $i => $nombre) {
            PosOpcion::firstOrCreate(
                ['tenant_id' => $tenant->id, 'grupo_id' => $puntoCoccion->id, 'nombre' => $nombre],
                ['precio_defecto' => 0, 'orden' => $i],
            );
        }

        $extras = PosOpcionGrupo::firstOrCreate(
            ['tenant_id' => $tenant->id, 'nombre' => 'Extras'],
            ['min_selecciones' => 0, 'max_selecciones' => null, 'obligatorio' => false, 'orden' => 1],
        );

        // "Extra queso" vinculado a un artículo del catálogo, si existe alguno con gestión de
        // stock: demuestra el descuento de stock sin línea propia (FR-052) sin inventar un
        // artículo nuevo si el tenant ya tiene uno adecuado.
        $articuloVinculado = Articulo::where('tenant_id', $tenant->id)
            ->where('gestion_stock', true)
            ->orderBy('id')
            ->first();

        PosOpcion::firstOrCreate(
            ['tenant_id' => $tenant->id, 'grupo_id' => $extras->id, 'nombre' => 'Extra queso'],
            ['precio_defecto' => 1.50, 'articulo_vinculado_id' => $articuloVinculado?->id, 'orden' => 0],
        );

        PosOpcion::firstOrCreate(
            ['tenant_id' => $tenant->id, 'grupo_id' => $extras->id, 'nombre' => 'Sin sal'],
            ['precio_defecto' => 0, 'orden' => 1],
        );
    }
}
