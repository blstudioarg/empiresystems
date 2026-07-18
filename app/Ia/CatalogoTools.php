<?php

namespace App\Ia;

use App\Ia\Tools\ToolAsistente;
use App\Models\User;

/**
 * Registro estático de las tools del asistente (feature 030, D9).
 *
 * `paraUsuario()` filtra por los permisos del usuario: solo esas tools se envían a la API y son
 * las únicas que el servidor acepta ejecutar. `resolver()` re-verifica el permiso al ejecutar/
 * confirmar (contrato de seguridad #2 — doble capa: el menú oculta, la ruta exige).
 *
 * Bloqueo estructural (D3): aquí NO existen tools de emitir/anular factura, registrar pago,
 * borrar registros ni gestionar configuración/usuarios/roles. Si no está en esta lista, el
 * asistente no puede hacerlo, pase lo que pase por prompt.
 */
class CatalogoTools
{
    /**
     * Clases de tool registradas. Añadir una feature = añadir su clase aquí.
     *
     * @var list<class-string<ToolAsistente>>
     */
    private const TOOLS = [
        // Lecturas (US2) y escrituras (US3) se registran en sus fases.
        Tools\BuscarClientes::class,
        Tools\BuscarArticulos::class,
        Tools\BuscarFacturas::class,
        Tools\BuscarPresupuestos::class,
        Tools\ResumenDatosNegocio::class,
        Tools\CrearCliente::class,
        Tools\EditarCliente::class,
        Tools\CrearArticulo::class,
        Tools\EditarArticulo::class,
        Tools\CrearPresupuesto::class,
        Tools\CrearFacturaBorrador::class,
        Tools\EditarFacturaBorrador::class,
    ];

    /**
     * Todas las tools instanciadas (sin filtrar). Uso interno y de tests.
     *
     * @return list<ToolAsistente>
     */
    public static function todas(): array
    {
        return array_map(static fn (string $clase): ToolAsistente => new $clase, self::TOOLS);
    }

    /**
     * Tools cuyo permiso tiene el usuario. Es la lista que se envía a la API.
     *
     * @return list<ToolAsistente>
     */
    public static function paraUsuario(User $usuario): array
    {
        return array_values(array_filter(
            self::todas(),
            static fn (ToolAsistente $tool): bool => $usuario->can($tool->permisoRequerido()),
        ));
    }

    /**
     * Resuelve una tool por nombre re-verificando el permiso del usuario. Devuelve null si la
     * tool no existe o el usuario no tiene el permiso requerido (contrato de seguridad #2).
     */
    public static function resolver(string $nombre, User $usuario): ?ToolAsistente
    {
        foreach (self::todas() as $tool) {
            if ($tool->nombre() === $nombre && $usuario->can($tool->permisoRequerido())) {
                return $tool;
            }
        }

        return null;
    }
}
