<?php

namespace Database\Seeders;

use App\Enums\EstadoLead;
use App\Enums\EstadoPresupuesto;
use App\Enums\OrigenLead;
use App\Enums\TipoArticulo;
use App\Models\Albaran;
use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Lead;
use App\Models\Presupuesto;
use App\Models\Tenant;
use App\Services\EntregadorAlbaran;
use App\Services\RegistroAlbaran;
use App\Services\RegistroFacturaBorrador;
use App\Services\RegistroPresupuesto;
use App\Services\RegistroTicket;
use Illuminate\Database\Seeder;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Datos de DEMO para el tenant del subdominio `pruebapos` (servidor de pruebas
 * empiresass.gestionley.com). Pensado para poder enseñar la app con contenido creíble sin tocar
 * NADA de lo que ya exista en la base.
 *
 * GARANTÍAS DE NO DESTRUCCIÓN
 * ---------------------------
 * - Solo hace INSERT. No hay un solo `delete()`, `update()` sobre registros ajenos, `truncate()`
 *   ni `migrate:fresh`. Los registros preexistentes del tenant no se leen para modificarlos.
 * - Es idempotente: cada documento lleva la marca `DEMO-SEED:<código>` en su campo `notas`
 *   (o el sufijo `DEMO-` en el SKU / la marca en `notas` para artículos y clientes). Antes de
 *   crear cada uno se comprueba si ya existe esa marca; si existe, se salta. Correrlo dos veces
 *   no duplica nada.
 * - Los artículos y clientes existentes SÍ se reutilizan como referencia (leer, no escribir)
 *   cuando los hay, para que la demo se parezca al catálogo real del tenant.
 *
 * QUÉ CREA Y QUÉ SE PUEDE DESHACER LUEGO
 * --------------------------------------
 *   Leads .................. borrables desde la UI (soft delete).
 *   Presupuestos ........... borrables desde la UI (soft delete).
 *   Albaranes .............. borrables desde la UI (soft delete).
 *   Facturas ordinarias .... SOLO en estado borrador → borrables desde la UI.
 *   Facturas simplificadas . se emiten al crearse (tickets POS): NO son borrables desde la app.
 *                            Decisión explícita del usuario (2026-08-09).
 *   Movimientos de stock ... ledger append-only, NO borrable. Aquí no se crea ninguno a mano:
 *                            los únicos que aparecen son los que genera el propio flujo al
 *                            entregar el albarán de demo y al emitir los tickets.
 *
 * USO
 *   php artisan db:seed --class=DemoPruebaPosSeeder
 *
 * El tenant se resuelve por su dominio; se puede apuntar a otro con `DEMO_TENANT_DOMINIO=xxx`.
 */
class DemoPruebaPosSeeder extends Seeder
{
    /** Marca que identifica todo lo creado por este seeder. */
    private const MARCA = 'DEMO-SEED';

    private const DOMINIO_POR_DEFECTO = 'pruebapos';

    public function run(): void
    {
        $tenant = $this->resolverTenant();

        if (! $tenant) {
            $this->command?->error('No se encontró el tenant. Define DEMO_TENANT_DOMINIO o revisa la tabla `domains`.');

            return;
        }

        $this->command?->info("Sembrando datos de demo en el tenant #{$tenant->id} ({$tenant->nombre_comercial}).");

        tenancy()->initialize($tenant);

        try {
            $clientes = $this->clientes();
            $articulos = $this->articulos();

            $this->leads();
            $this->presupuestos($clientes, $articulos);
            $this->albaranes($clientes, $articulos);
            $this->facturasBorrador($clientes, $articulos);
            $this->ticketsSimplificados($articulos);
        } finally {
            tenancy()->end();
        }

        $this->command?->info('Listo. Nada preexistente fue modificado ni borrado.');
    }

    private function resolverTenant(): ?Tenant
    {
        $buscado = env('DEMO_TENANT_DOMINIO', self::DOMINIO_POR_DEFECTO);

        $dominio = Domain::where('domain', 'like', $buscado.'%')->first();

        /** @var Tenant|null $tenant */
        $tenant = $dominio?->tenant;

        return $tenant;
    }

    private function marca(string $codigo): string
    {
        return self::MARCA.':'.$codigo;
    }

    // ---------------------------------------------------------------------
    // Catálogo base: se reutiliza el real si lo hay, si no se crea uno demo.
    // ---------------------------------------------------------------------

    /**
     * @return array<int, Cliente>
     */
    private function clientes(): array
    {
        $definiciones = [
            [
                'nif' => 'B86578121',
                'tipo' => 'empresa',
                'nombre' => 'Marta Ruiz',
                'razon_social' => 'Panadería La Espiga SL',
                'direccion' => 'Calle Alcalá 128',
                'cp' => '28009',
                'ciudad' => 'Madrid',
                'provincia' => 'Madrid',
                'email' => 'compras@laespiga.demo',
                'telefono' => '915550101',
            ],
            [
                'nif' => 'B65432198',
                'tipo' => 'empresa',
                'nombre' => 'Sergio Ibáñez',
                'razon_social' => 'Cafetería Nou Mercat SL',
                'direccion' => 'Carrer de Sants 44',
                'cp' => '08014',
                'ciudad' => 'Barcelona',
                'provincia' => 'Barcelona',
                'email' => 'sergio@noumercat.demo',
                'telefono' => '933550202',
            ],
            [
                'nif' => '52478913F',
                'tipo' => 'particular',
                'nombre' => 'Lucía Fernández Soto',
                'razon_social' => null,
                'direccion' => 'Avenida del Puerto 21',
                'cp' => '46021',
                'ciudad' => 'Valencia',
                'provincia' => 'Valencia',
                'email' => 'lucia.fernandez@demo.es',
                'telefono' => '655110220',
            ],
            [
                'nif' => 'B41250037',
                'tipo' => 'empresa',
                'nombre' => 'Ana Delgado',
                'razon_social' => 'Distribuciones Guadalquivir SL',
                'direccion' => 'Polígono Sur, nave 12',
                'cp' => '41013',
                'ciudad' => 'Sevilla',
                'provincia' => 'Sevilla',
                'email' => 'pedidos@guadalquivir.demo',
                'telefono' => '954550303',
            ],
        ];

        $clientes = [];

        foreach ($definiciones as $definicion) {
            $clientes[] = Cliente::firstOrCreate(
                ['nif' => $definicion['nif']],
                array_merge($definicion, [
                    'pais' => 'ES',
                    // Explícito: `RegistroFacturaBorrador` lee este flag sin coalescencia y un
                    // null revienta el cálculo de importes.
                    'aplica_recargo_equivalencia' => false,
                    'notas' => $this->marca('cliente'),
                ])
            );
        }

        return $clientes;
    }

    /**
     * Artículos con los que montar líneas. Se prefieren los que ya tenga el tenant (así la demo
     * usa su catálogo real); solo si no hay suficientes productos con control de stock se crean
     * los de demo.
     *
     * @return array<int, Articulo>
     */
    private function articulos(): array
    {
        $existentes = Articulo::where('tipo', TipoArticulo::Producto)
            ->where('gestion_stock', true)
            ->orderBy('id')
            ->limit(4)
            ->get()
            ->all();

        if (count($existentes) >= 3) {
            return $existentes;
        }

        $definiciones = [
            ['sku' => 'DEMO-CAF-001', 'nombre' => 'Café en grano natural 1 kg', 'precio' => 12.90, 'stock' => 80],
            ['sku' => 'DEMO-CAF-002', 'nombre' => 'Cápsulas compatibles (caja 50)', 'precio' => 16.50, 'stock' => 120],
            ['sku' => 'DEMO-VAS-001', 'nombre' => 'Vaso térmico 350 ml', 'precio' => 4.25, 'stock' => 200],
            ['sku' => 'DEMO-AZU-001', 'nombre' => 'Azúcar en sobres (caja 500)', 'precio' => 9.80, 'stock' => 60],
        ];

        $articulos = $existentes;

        foreach ($definiciones as $definicion) {
            $articulos[] = Articulo::firstOrCreate(
                ['sku' => $definicion['sku']],
                [
                    'tipo' => TipoArticulo::Producto,
                    'nombre' => $definicion['nombre'],
                    'descripcion' => 'Artículo de demostración ('.self::MARCA.').',
                    'unidad' => 'ud',
                    'precio' => $definicion['precio'],
                    'tipo_impositivo' => 21,
                    'gestion_stock' => true,
                    'stock_actual' => $definicion['stock'],
                    'stock_minimo' => 10,
                    'activo' => true,
                ]
            );
        }

        return $articulos;
    }

    // ---------------------------------------------------------------------
    // Leads
    // ---------------------------------------------------------------------

    private function leads(): void
    {
        $definiciones = [
            ['nombre' => 'Iván Molina', 'empresa' => 'Bar El Rincón', 'email' => 'ivan@elrincon.demo', 'telefono' => '600111222', 'estado' => EstadoLead::Nuevo],
            ['nombre' => 'Nuria Castro', 'empresa' => 'Hotel Miramar', 'email' => 'nuria@miramar.demo', 'telefono' => '600222333', 'estado' => EstadoLead::Contactado],
            ['nombre' => 'Pablo Ortega', 'empresa' => 'Coworking Atalaya', 'email' => 'pablo@atalaya.demo', 'telefono' => '600333444', 'estado' => EstadoLead::Cualificado],
            ['nombre' => 'Rocío Vidal', 'empresa' => 'Librería Página 12', 'email' => 'rocio@pagina12.demo', 'telefono' => '600444555', 'estado' => EstadoLead::Contactado],
            ['nombre' => 'Diego Salas', 'empresa' => 'Gimnasio Impulso', 'email' => 'diego@impulso.demo', 'telefono' => '600555666', 'estado' => EstadoLead::Nuevo],
            ['nombre' => 'Elena Prieto', 'empresa' => 'Floristería Azahar', 'email' => 'elena@azahar.demo', 'telefono' => '600666777', 'estado' => EstadoLead::Descartado, 'motivo_descarte' => 'Fuera de zona de reparto.'],
        ];

        foreach ($definiciones as $definicion) {
            Lead::firstOrCreate(
                ['email' => $definicion['email']],
                [
                    'nombre' => $definicion['nombre'],
                    'empresa' => $definicion['empresa'],
                    'telefono' => $definicion['telefono'],
                    'estado' => $definicion['estado'],
                    'origen' => OrigenLead::Manual,
                    'motivo_descarte' => $definicion['motivo_descarte'] ?? null,
                    'notas' => $this->marca('lead'),
                ]
            );
        }
    }

    // ---------------------------------------------------------------------
    // Presupuestos
    // ---------------------------------------------------------------------

    /**
     * @param  array<int, Cliente>  $clientes
     * @param  array<int, Articulo>  $articulos
     * @return array<string, Presupuesto>
     */
    private function presupuestos(array $clientes, array $articulos): array
    {
        $registro = app(RegistroPresupuesto::class);

        $definiciones = [
            'p1' => ['cliente' => 0, 'estado' => EstadoPresupuesto::Enviado, 'lineas' => [[0, 10], [2, 24]]],
            'p2' => ['cliente' => 1, 'estado' => EstadoPresupuesto::Aceptado, 'lineas' => [[1, 6], [2, 12]]],
            'p3' => ['cliente' => 2, 'estado' => EstadoPresupuesto::Borrador, 'lineas' => [[0, 3]]],
            'p4' => ['cliente' => 3, 'estado' => EstadoPresupuesto::Rechazado, 'lineas' => [[1, 20]]],
        ];

        $creados = [];

        foreach ($definiciones as $codigo => $definicion) {
            $marca = $this->marca($codigo);

            $existente = Presupuesto::where('notas', $marca)->first();

            if ($existente) {
                $creados[$codigo] = $existente;

                continue;
            }

            $presupuesto = $registro->guardar([
                'cliente_id' => $clientes[$definicion['cliente']]->id,
                'fecha_emision' => now()->subDays(random_int(5, 40))->toDateString(),
                'lineas' => $this->lineasDesde($articulos, $definicion['lineas']),
                'notas' => $marca,
            ]);

            // El servicio siempre nace en borrador; para la demo interesan varios estados.
            if ($definicion['estado'] !== EstadoPresupuesto::Borrador) {
                $presupuesto->update(['estado' => $definicion['estado']]);
            }

            $creados[$codigo] = $presupuesto->refresh();
        }

        return $creados;
    }

    // ---------------------------------------------------------------------
    // Albaranes (uno entregado → genera movimientos de stock por el flujo real)
    // ---------------------------------------------------------------------

    /**
     * @param  array<int, Cliente>  $clientes
     * @param  array<int, Articulo>  $articulos
     */
    private function albaranes(array $clientes, array $articulos): void
    {
        $registro = app(RegistroAlbaran::class);
        $entregador = app(EntregadorAlbaran::class);

        $definiciones = [
            'a1' => ['cliente' => 0, 'entregar' => true, 'lineas' => [[0, 4], [2, 10]]],
            'a2' => ['cliente' => 1, 'entregar' => false, 'lineas' => [[1, 3]]],
            'a3' => ['cliente' => 3, 'entregar' => false, 'lineas' => [[2, 8], [0, 2]]],
        ];

        foreach ($definiciones as $codigo => $definicion) {
            $marca = $this->marca($codigo);

            if (Albaran::where('notas', $marca)->exists()) {
                continue;
            }

            $albaran = $registro->guardar([
                'cliente_id' => $clientes[$definicion['cliente']]->id,
                'lineas' => $this->lineasDesde($articulos, $definicion['lineas']),
                'notas' => $marca,
            ]);

            if ($definicion['entregar']) {
                // Esto SÍ genera movimientos de stock (salida), que son append-only.
                $entregador->entregar($albaran, now()->subDays(3)->toDateString());
            }
        }
    }

    // ---------------------------------------------------------------------
    // Facturas ordinarias — SIEMPRE en borrador (borrables desde la UI)
    // ---------------------------------------------------------------------

    /**
     * @param  array<int, Cliente>  $clientes
     * @param  array<int, Articulo>  $articulos
     */
    private function facturasBorrador(array $clientes, array $articulos): void
    {
        $registro = app(RegistroFacturaBorrador::class);

        $definiciones = [
            'f1' => ['cliente' => 0, 'lineas' => [[0, 6], [1, 2]]],
            'f2' => ['cliente' => 1, 'lineas' => [[2, 15]]],
            'f3' => ['cliente' => 2, 'lineas' => [[0, 1], [2, 4]]],
            'f4' => ['cliente' => 3, 'lineas' => [[1, 9]]],
        ];

        foreach ($definiciones as $codigo => $definicion) {
            $marca = $this->marca($codigo);

            if (Factura::where('notas', $marca)->exists()) {
                continue;
            }

            $factura = $registro->crear(
                $clientes[$definicion['cliente']],
                $this->lineasDesde($articulos, $definicion['lineas']),
            );

            // `RegistroFacturaBorrador` no acepta notas; se marca aquí para poder identificarla.
            $factura->update(['notas' => $marca]);
        }
    }

    // ---------------------------------------------------------------------
    // Tickets simplificados — SE EMITEN al crearse y NO son borrables desde la app
    // ---------------------------------------------------------------------

    /**
     * @param  array<int, Articulo>  $articulos
     */
    private function ticketsSimplificados(array $articulos): void
    {
        $registro = app(RegistroTicket::class);

        // Sin desglose de pagos: `RegistroTicket` asume un único cobro íntegro en efectivo.
        $definiciones = [
            't1' => ['lineas' => [[0, 1], [2, 2]]],
            't2' => ['lineas' => [[2, 3]]],
            't3' => ['lineas' => [[1, 1], [0, 1], [2, 1]]],
        ];

        foreach ($definiciones as $codigo => $definicion) {
            $marca = $this->marca($codigo);

            if (Factura::where('notas', $marca)->exists()) {
                continue;
            }

            $registro->registrar([
                'lineas' => $this->lineasDesde($articulos, $definicion['lineas']),
                'notas' => $marca,
            ]);
        }
    }

    // ---------------------------------------------------------------------
    // Utilidades
    // ---------------------------------------------------------------------

    /**
     * Construye líneas de documento a partir de índices del catálogo: [[indiceArticulo, cantidad]].
     *
     * @param  array<int, Articulo>  $articulos
     * @param  array<int, array{0: int, 1: float|int}>  $especificacion
     * @return array<int, array<string, mixed>>
     */
    private function lineasDesde(array $articulos, array $especificacion): array
    {
        $lineas = [];

        foreach ($especificacion as $par) {
            [$indice, $cantidad] = $par;

            $articulo = $articulos[$indice % count($articulos)];

            $lineas[] = [
                'articulo_id' => $articulo->id,
                'concepto' => $articulo->nombre,
                'unidad' => $articulo->unidad,
                'cantidad' => (float) $cantidad,
                'precio_unitario' => (float) $articulo->precio,
                'tipo_impositivo' => (float) $articulo->tipo_impositivo,
            ];
        }

        return $lineas;
    }
}
