<?php

namespace App\Support;

/**
 * Cuenta las páginas de un PDF leyendo los objetos `/Type /Page` del propio fichero, sin Imagick ni
 * Ghostscript: el hosting compartido no los tiene y añadirlos violaría el Principio V.
 *
 * Es una heurística, no un parser: si el PDF está comprimido con object streams o cifrado, el
 * recuento no aparece en claro. En ese caso devuelve `null` y el llamador **no bloquea** la subida
 * (research D7): más vale aceptar un PDF largo que rechazar uno válido.
 */
class ContadorPaginasPdf
{
    public function contar(string $rutaAbsoluta): ?int
    {
        if (! is_readable($rutaAbsoluta)) {
            return null;
        }

        $contenido = @file_get_contents($rutaAbsoluta);

        if ($contenido === false || ! str_starts_with($contenido, '%PDF-')) {
            return null;
        }

        // `/Count N` del nodo raíz de páginas es el dato explícito cuando está en claro.
        if (preg_match_all('/\/Type\s*\/Pages\b[^>]*?\/Count\s+(\d+)/s', $contenido, $coincidencias)) {
            $maximo = max(array_map('intval', $coincidencias[1]));

            if ($maximo > 0) {
                return $maximo;
            }
        }

        // Si no, contar los objetos de página individuales. El `(?![s])` evita contar `/Type /Pages`
        // (el nodo contenedor) como si fuera una página más.
        $paginas = preg_match_all('/\/Type\s*\/Page(?![sA-Za-z])/', $contenido);

        return $paginas > 0 ? $paginas : null;
    }
}
