<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Resuelve la sección de aterrizaje ("landing") de un usuario que abre la raíz `/` sin tener
 * `ver-dashboard` (doc 09, Cambio 5). Antes ese caso redirigía siempre a "Mi jornada"; ahora que
 * "Fichar"/"Mi jornada" también son permisos gateables, la raíz debe mandar al usuario a la
 * PRIMERA sección para la que tiene permiso, en el mismo orden del menú lateral.
 *
 * Fallback final: el perfil (`profile.show`), única sección universal sin permiso — garantiza que
 * cualquier usuario autenticado tenga un destino aunque no tenga ningún permiso de vista.
 *
 * Nota: `/mi-jornada` exige además un perfil de miembro de equipo (abort_if en su controller). No
 * es un problema en la práctica porque `ver-fichar` la precede en esta lista y `/fichajes` siempre
 * responde 200 con su permiso, así que el usuario base aterriza en Fichar, no en Mi jornada.
 */
class ResolvedorLanding
{
    /**
     * Permiso → ruta índice de su sección, en orden de menú. Solo secciones con una vista real de
     * aterrizaje (se omiten los permisos de acción como ver-facturas-crear y el modificador de
     * alcance ver-informes-equipo, que no abren pantalla).
     *
     * @var array<string, string>
     */
    private const MAPA = [
        'ver-dashboard' => 'dashboard',
        'ver-fichar' => 'fichajes.index',
        'ver-mi-jornada' => 'mi-jornada.index',
        'ver-jornada' => 'jornada.index',
        'ver-calendario' => 'calendario.index',
        'ver-miembros' => 'miembros-equipo.index',
        'ver-horarios' => 'horarios.index',
        'ver-alertas' => 'alertas.index',
        'ver-clientes' => 'clientes.index',
        'ver-leads' => 'leads.index',
        'ver-oportunidades' => 'oportunidades.index',
        'ver-presupuestos' => 'presupuestos.index',
        'ver-albaranes' => 'albaranes.index',
        'ver-informes-comerciales' => 'informes-comerciales.index',
        'ver-articulos' => 'articulos.index',
        'ver-stock' => 'stock.index',
        'ver-proveedores' => 'proveedores.index',
        'ver-compras' => 'compras.index',
        'ver-facturas' => 'facturas.index',
        'ver-pos' => 'pos.index',
        'ver-archivos' => 'archivos.index',
        'ver-campanas' => 'campanas.index',
        'ver-plantillas-email' => 'plantillas-email.index',
        'ver-usuarios' => 'usuarios.index',
        'ver-roles' => 'roles.index',
        'ver-configuracion' => 'configuracion.show',
        'ver-logs' => 'logs.index',
    ];

    private const FALLBACK = 'profile.show';

    /**
     * Nombre de la primera ruta con permiso, o el fallback (perfil).
     */
    public function rutaPara(User $user): string
    {
        foreach (self::MAPA as $permiso => $ruta) {
            if (Route::has($ruta) && $user->can($permiso)) {
                return $ruta;
            }
        }

        return self::FALLBACK;
    }

    /**
     * URL de aterrizaje del usuario.
     */
    public function urlPara(User $user): string
    {
        return route($this->rutaPara($user));
    }
}
