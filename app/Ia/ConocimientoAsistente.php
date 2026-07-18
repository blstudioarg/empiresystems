<?php

namespace App\Ia;

use App\Models\User;
use App\Support\CatalogoPermisos;

/**
 * Ensambla el system prompt del asistente (feature 030, D8).
 *
 * = reglas fijas del asistente (idioma, capacidades/límites, rechazo de datos sensibles,
 *   flujo de confirmación) + concatenación determinista (orden alfabético) de los
 *   `resources/ia/conocimiento/*.md` + contexto del usuario (nombre, secciones con permiso).
 *
 * Invariante SC-007: añadir una feature = añadir un archivo .md, sin tocar el resto.
 */
class ConocimientoAsistente
{
    public function systemPrompt(User $usuario): string
    {
        return implode("\n\n", array_filter([
            $this->reglasFijas(),
            $this->baseConocimiento(),
            $this->contextoUsuario($usuario),
        ]));
    }

    private function reglasFijas(): string
    {
        return <<<'TXT'
        # Asistente de Empire Systems CRM

        Sos el asistente integrado de Empire Systems, un CRM/facturación para España. Ayudás a los
        usuarios a entender y usar la aplicación, a consultar sus datos de negocio y a crear o editar
        ciertas fichas y documentos.

        ## Reglas
        - Respondé SIEMPRE en español, de forma clara y concisa.
        - Solo podés ayudar con esta aplicación. Si te piden algo ajeno, redirigí amablemente.
        - NUNCA reveles datos sensibles de configuración interna (claves de API, contraseñas,
          certificados) ni datos de otros tenants. No tenés acceso a ellos y no debés inventarlos.
        - Basá tus respuestas de funcionamiento en la base de conocimiento incluida más abajo.
        - Usá las tools disponibles para consultar datos reales cuando el usuario lo pida; no inventes
          cifras ni registros.

        ## Acciones que NO podés hacer
        Emitir o anular facturas, registrar pagos, borrar registros, ni tocar configuración, usuarios
        o roles. No dispongo de herramientas para eso: si te lo piden, explicá con cortesía que esas
        acciones se hacen manualmente desde la pantalla correspondiente, por seguridad.

        ## Crear o editar (clientes, artículos, presupuestos, facturas en borrador)
        Cuando el usuario pida crear o editar algo, usá la tool correspondiente. Estas acciones NO se
        ejecutan de inmediato: se le muestra al usuario un resumen con botones Confirmar/Cancelar, y
        solo se ejecutan cuando el usuario confirma. No afirmes que algo quedó creado hasta que el
        sistema te informe la confirmación. Las facturas se crean SIEMPRE en estado borrador; los
        importes los calcula el servidor, no vos.
        TXT;
    }

    private function baseConocimiento(): string
    {
        $dir = resource_path('ia/conocimiento');

        if (! is_dir($dir)) {
            return '';
        }

        $archivos = glob($dir.'/*.md') ?: [];
        sort($archivos, SORT_STRING); // determinista → estable para prompt caching

        $partes = [];
        foreach ($archivos as $archivo) {
            $contenido = trim((string) file_get_contents($archivo));
            if ($contenido !== '') {
                $partes[] = $contenido;
            }
        }

        if ($partes === []) {
            return '';
        }

        return "# Base de conocimiento\n\n".implode("\n\n---\n\n", $partes);
    }

    private function contextoUsuario(User $usuario): string
    {
        $secciones = [];
        foreach (CatalogoPermisos::todos() as $permiso) {
            if ($usuario->can($permiso['clave'])) {
                $secciones[] = $permiso['etiqueta'];
            }
        }

        $listaSecciones = $secciones === []
            ? 'ninguna sección de datos (solo puede consultar el funcionamiento general)'
            : implode(', ', array_unique($secciones));

        $nombre = $usuario->name;

        return <<<TXT
        # Contexto del usuario actual
        - Nombre: {$nombre}
        - Secciones a las que tiene acceso: {$listaSecciones}.

        Solo podés consultar o modificar datos de las secciones a las que este usuario tiene acceso.
        Si pide algo de una sección que no figura arriba, explicá que no tiene permiso para esa parte.
        TXT;
    }
}
