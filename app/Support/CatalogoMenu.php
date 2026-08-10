<?php

namespace App\Support;

/**
 * Fuente de verdad del catálogo del menú lateral del tenant (feature 036, data-model.md §1).
 * Transcrito uno a uno desde `resources/views/partials/sidebar.blade.php`: 10 elementos de primer
 * nivel + 26 entradas de segundo nivel = 36. No se edita desde la aplicación (FR-002); el icono y
 * la ruta declarados aquí tampoco son personalizables (FR-003) — solo la etiqueta y el orden lo
 * son, vía {@see MenuTenant}.
 *
 * Un grupo con hijos declara `permiso` en null: su visibilidad se deriva de que al menos uno de
 * sus hijos sea visible (research.md D2, regla 2), igual que el `@canany` que sustituye.
 */
class CatalogoMenu
{
    /**
     * @var list<array{clave: string, etiqueta: string, icono: ?string, ruta: ?string, permiso: ?string, hijos: list<array{clave: string, etiqueta: string, icono: null, ruta: string, permiso: ?string, hijos: array{}}>}>
     */
    private const CATALOGO = [
        [
            'clave' => 'inicio', 'etiqueta' => 'Inicio', 'icono' => 'home',
            'ruta' => 'dashboard', 'permiso' => 'ver-dashboard', 'hijos' => [],
        ],
        [
            'clave' => 'control-fichaje', 'etiqueta' => 'Control de fichaje',
            'icono' => 'wired-outline-1846-employee-working-hover-working',
            'ruta' => null, 'permiso' => null,
            'hijos' => [
                ['clave' => 'fichar', 'etiqueta' => 'Fichar', 'icono' => null, 'ruta' => 'fichajes.index', 'permiso' => 'ver-fichar', 'hijos' => []],
                ['clave' => 'mi-jornada', 'etiqueta' => 'Mi jornada', 'icono' => null, 'ruta' => 'mi-jornada.index', 'permiso' => 'ver-mi-jornada', 'hijos' => []],
                ['clave' => 'jornada', 'etiqueta' => 'Jornada', 'icono' => null, 'ruta' => 'jornada.index', 'permiso' => 'ver-jornada', 'hijos' => []],
                ['clave' => 'calendario', 'etiqueta' => 'Calendario', 'icono' => null, 'ruta' => 'calendario.index', 'permiso' => 'ver-calendario', 'hijos' => []],
                ['clave' => 'miembros', 'etiqueta' => 'Miembros', 'icono' => null, 'ruta' => 'miembros-equipo.index', 'permiso' => 'ver-miembros', 'hijos' => []],
                ['clave' => 'horarios', 'etiqueta' => 'Horarios', 'icono' => null, 'ruta' => 'horarios.index', 'permiso' => 'ver-horarios', 'hijos' => []],
                ['clave' => 'alertas', 'etiqueta' => 'Alertas', 'icono' => null, 'ruta' => 'alertas.index', 'permiso' => 'ver-alertas', 'hijos' => []],
            ],
        ],
        [
            'clave' => 'clientes', 'etiqueta' => 'Clientes', 'icono' => 'empresa',
            'ruta' => null, 'permiso' => null,
            'hijos' => [
                ['clave' => 'cartera-clientes', 'etiqueta' => 'Cartera de clientes', 'icono' => null, 'ruta' => 'clientes.index', 'permiso' => 'ver-clientes', 'hijos' => []],
            ],
        ],
        [
            'clave' => 'crm', 'etiqueta' => 'CRM', 'icono' => 'wired-outline-456-handshake-deal-hover-pinch',
            'ruta' => null, 'permiso' => null,
            'hijos' => [
                ['clave' => 'leads', 'etiqueta' => 'Leads', 'icono' => null, 'ruta' => 'leads.index', 'permiso' => 'ver-leads', 'hijos' => []],
                ['clave' => 'oportunidades', 'etiqueta' => 'Oportunidades', 'icono' => null, 'ruta' => 'oportunidades.index', 'permiso' => 'ver-oportunidades', 'hijos' => []],
                ['clave' => 'presupuestos', 'etiqueta' => 'Presupuestos', 'icono' => null, 'ruta' => 'presupuestos.index', 'permiso' => 'ver-presupuestos', 'hijos' => []],
                ['clave' => 'albaranes', 'etiqueta' => 'Albaranes', 'icono' => null, 'ruta' => 'albaranes.index', 'permiso' => 'ver-albaranes', 'hijos' => []],
                ['clave' => 'informes-comerciales', 'etiqueta' => 'Informes comerciales', 'icono' => null, 'ruta' => 'informes-comerciales.index', 'permiso' => 'ver-informes-comerciales', 'hijos' => []],
            ],
        ],
        [
            'clave' => 'stock', 'etiqueta' => 'Stock', 'icono' => 'box',
            'ruta' => null, 'permiso' => null,
            'hijos' => [
                ['clave' => 'catalogo', 'etiqueta' => 'Catálogo', 'icono' => null, 'ruta' => 'articulos.index', 'permiso' => 'ver-articulos', 'hijos' => []],
                ['clave' => 'kardex', 'etiqueta' => 'Kardex', 'icono' => null, 'ruta' => 'stock.index', 'permiso' => 'ver-stock', 'hijos' => []],
                ['clave' => 'proveedores', 'etiqueta' => 'Proveedores', 'icono' => null, 'ruta' => 'proveedores.index', 'permiso' => 'ver-proveedores', 'hijos' => []],
                ['clave' => 'compras', 'etiqueta' => 'Compras', 'icono' => null, 'ruta' => 'compras.index', 'permiso' => 'ver-compras', 'hijos' => []],
            ],
        ],
        [
            'clave' => 'facturas', 'etiqueta' => 'Facturas', 'icono' => 'invoice',
            'ruta' => null, 'permiso' => null,
            'hijos' => [
                ['clave' => 'facturas-listado', 'etiqueta' => 'Facturas', 'icono' => null, 'ruta' => 'facturas.index', 'permiso' => 'ver-facturas', 'hijos' => []],
                ['clave' => 'facturas-crear', 'etiqueta' => 'Crear factura', 'icono' => null, 'ruta' => 'facturas.create', 'permiso' => 'ver-facturas-crear', 'hijos' => []],
            ],
        ],
        [
            'clave' => 'pos', 'etiqueta' => 'POS', 'icono' => 'ticket',
            'ruta' => null, 'permiso' => null,
            'hijos' => [
                ['clave' => 'pos-listado', 'etiqueta' => 'Facturas simplificadas', 'icono' => null, 'ruta' => 'pos.index', 'permiso' => 'ver-pos', 'hijos' => []],
                ['clave' => 'pos-crear', 'etiqueta' => 'Crear ticket', 'icono' => null, 'ruta' => 'pos.create', 'permiso' => 'ver-pos-crear', 'hijos' => []],
            ],
        ],
        [
            'clave' => 'archivos', 'etiqueta' => 'Archivos', 'icono' => 'wired-outline-89-document-plus-hover-swipe',
            'ruta' => 'archivos.index', 'permiso' => 'ver-archivos', 'hijos' => [],
        ],
        [
            'clave' => 'marketing', 'etiqueta' => 'Marketing', 'icono' => 'wired-outline-1027-megaphone-media-hover-pinch',
            'ruta' => null, 'permiso' => null,
            'hijos' => [
                ['clave' => 'campanas', 'etiqueta' => 'Campañas', 'icono' => null, 'ruta' => 'campanas.index', 'permiso' => 'ver-campanas', 'hijos' => []],
                ['clave' => 'campanas-crear', 'etiqueta' => 'Nueva campaña', 'icono' => null, 'ruta' => 'campanas.create', 'permiso' => 'ver-campanas-crear', 'hijos' => []],
                ['clave' => 'plantillas-email', 'etiqueta' => 'Plantillas de email', 'icono' => null, 'ruta' => 'plantillas-email.index', 'permiso' => 'ver-plantillas-email', 'hijos' => []],
            ],
        ],
        [
            'clave' => 'usuarios', 'etiqueta' => 'Usuarios', 'icono' => 'person',
            'ruta' => null, 'permiso' => null,
            'hijos' => [
                ['clave' => 'usuarios-listado', 'etiqueta' => 'Usuarios', 'icono' => null, 'ruta' => 'usuarios.index', 'permiso' => 'ver-usuarios', 'hijos' => []],
                ['clave' => 'roles', 'etiqueta' => 'Roles', 'icono' => null, 'ruta' => 'roles.index', 'permiso' => 'ver-roles', 'hijos' => []],
            ],
        ],
    ];

    /**
     * @return list<array{clave: string, etiqueta: string, icono: ?string, ruta: ?string, permiso: ?string, hijos: array}>
     */
    public static function catalogo(): array
    {
        return self::CATALOGO;
    }

    /**
     * Claves de los grupos de primer nivel, en orden. Es el conjunto válido para el nivel `_raiz`
     * de la personalización.
     *
     * @return list<string>
     */
    public static function clavesGrupos(): array
    {
        return array_column(self::CATALOGO, 'clave');
    }

    /**
     * Accesor plano clave → elemento (grupos y entradas comparten espacio de nombres, INV-1),
     * para las validaciones y la fusión de {@see MenuTenant}.
     *
     * @return array<string, array{clave: string, etiqueta: string, icono: ?string, ruta: ?string, permiso: ?string, hijos: array}>
     */
    public static function planos(): array
    {
        $planos = [];

        foreach (self::CATALOGO as $grupo) {
            $planos[$grupo['clave']] = $grupo;

            foreach ($grupo['hijos'] as $hijo) {
                $planos[$hijo['clave']] = $hijo;
            }
        }

        return $planos;
    }

    public static function existe(string $clave): bool
    {
        return array_key_exists($clave, self::planos());
    }
}
