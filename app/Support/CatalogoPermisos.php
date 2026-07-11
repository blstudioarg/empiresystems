<?php

namespace App\Support;

/**
 * Fuente de verdad del catálogo de permisos de la aplicación (feature 027, research.md D3).
 *
 * Granularidad = una vista/sección del sidebar. La tabla `permissions` de spatie solo persiste
 * la clave; la etiqueta y el módulo (para la UI de gestión de roles) se resuelven siempre desde
 * aquí. Añadir una entrada nueva de menú obliga a añadir su permiso aquí y re-correr el seeder
 * (procedimiento FR-013, docs/04-front-guidelines.md).
 */
class CatalogoPermisos
{
    /**
     * @var list<array{clave: string, etiqueta: string, modulo: string}>
     */
    private const PERMISOS = [
        ['clave' => 'ver-dashboard', 'etiqueta' => 'Dashboard', 'modulo' => 'Dashboard'],
        ['clave' => 'ver-jornada', 'etiqueta' => 'Control de fichaje', 'modulo' => 'Control de fichaje'],
        ['clave' => 'ver-clientes', 'etiqueta' => 'Clientes', 'modulo' => 'Clientes'],
        ['clave' => 'ver-leads', 'etiqueta' => 'Leads', 'modulo' => 'CRM'],
        ['clave' => 'ver-oportunidades', 'etiqueta' => 'Oportunidades', 'modulo' => 'CRM'],
        ['clave' => 'ver-presupuestos', 'etiqueta' => 'Presupuestos', 'modulo' => 'CRM'],
        ['clave' => 'ver-albaranes', 'etiqueta' => 'Albaranes', 'modulo' => 'CRM'],
        ['clave' => 'ver-articulos', 'etiqueta' => 'Catálogo', 'modulo' => 'Stock'],
        ['clave' => 'ver-stock', 'etiqueta' => 'Kardex', 'modulo' => 'Stock'],
        ['clave' => 'ver-proveedores', 'etiqueta' => 'Proveedores', 'modulo' => 'Stock'],
        ['clave' => 'ver-compras', 'etiqueta' => 'Compras', 'modulo' => 'Stock'],
        ['clave' => 'ver-facturas', 'etiqueta' => 'Facturas', 'modulo' => 'Facturas'],
        ['clave' => 'ver-pos', 'etiqueta' => 'POS', 'modulo' => 'POS'],
        ['clave' => 'ver-archivos', 'etiqueta' => 'Archivos', 'modulo' => 'Archivos'],
        ['clave' => 'ver-campanas', 'etiqueta' => 'Campañas', 'modulo' => 'Marketing'],
        ['clave' => 'ver-plantillas-email', 'etiqueta' => 'Plantillas de email', 'modulo' => 'Marketing'],
        ['clave' => 'ver-usuarios', 'etiqueta' => 'Usuarios', 'modulo' => 'Usuarios'],
        ['clave' => 'ver-roles', 'etiqueta' => 'Roles', 'modulo' => 'Roles'],
        ['clave' => 'ver-configuracion', 'etiqueta' => 'Configuración', 'modulo' => 'Configuración'],
        ['clave' => 'ver-logs', 'etiqueta' => 'Logs de actividad', 'modulo' => 'Logs'],
        ['clave' => 'ver-bancos', 'etiqueta' => 'Bancos', 'modulo' => 'Bancos'],
    ];

    /**
     * Permisos que NO recibe el rol "Usuario" base migrado/creado por defecto (RN-07, SC-005):
     * gestión de fichajes, roles, usuarios, configuración y logs quedan reservados al Administrador.
     */
    private const EXCLUIDOS_USUARIO_BASE = [
        'ver-jornada',
        'ver-roles',
        'ver-usuarios',
        'ver-configuracion',
        'ver-logs',
    ];

    /**
     * @return list<array{clave: string, etiqueta: string, modulo: string}>
     */
    public static function todos(): array
    {
        return self::PERMISOS;
    }

    /**
     * @return list<string>
     */
    public static function claves(): array
    {
        return array_column(self::PERMISOS, 'clave');
    }

    /**
     * Catálogo agrupado por módulo, en el orden de declaración, para la UI de roles.
     *
     * @return list<array{modulo: string, permisos: list<array{name: string, label: string}>}>
     */
    public static function porModulo(): array
    {
        $grupos = [];

        foreach (self::PERMISOS as $permiso) {
            $grupos[$permiso['modulo']][] = [
                'name' => $permiso['clave'],
                'label' => $permiso['etiqueta'],
            ];
        }

        $resultado = [];
        foreach ($grupos as $modulo => $permisos) {
            $resultado[] = ['modulo' => $modulo, 'permisos' => $permisos];
        }

        return $resultado;
    }

    /**
     * Claves del rol "Usuario" base (catálogo menos los permisos de gestión).
     *
     * @return list<string>
     */
    public static function clavesUsuarioBase(): array
    {
        return array_values(array_diff(self::claves(), self::EXCLUIDOS_USUARIO_BASE));
    }
}
