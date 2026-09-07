<?php

namespace App\Support;

/**
 * Tipos de material que el asistente acepta para una importación, y de qué manera hay que leerlos
 * (feature 046, FR-002/FR-003).
 *
 * Hay exactamente dos orígenes: `hoja` —lo que ya sabe leer `ImportadorExcel` sin gastar una
 * llamada al proveedor— y `documento` —lo que hay que interpretar con IA—. La distinción no es
 * cosmética: decide si la importación cuesta dinero, si depende de que haya clave configurada y si
 * aplica el tope de páginas de `config/importacion.php`.
 *
 * Todo rechazo sale de aquí con un mensaje en español que explica qué pasó (FR-003, SC-007): un
 * tipo no admitido nunca puede acabar en una excepción cruda ni en un 500.
 */
class MaterialImportable
{
    public const ORIGEN_HOJA = 'hoja';

    public const ORIGEN_DOCUMENTO = 'documento';

    /** Extensiones que `ImportadorExcel` lee directamente, sin proveedor de IA de por medio. */
    private const TIPOS_HOJA = ['xlsx', 'xls', 'csv'];

    /** Extensiones que hay que interpretar (research D3). El texto plano también: no es tabular. */
    private const TIPOS_DOCUMENTO = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'txt'];

    /**
     * Extensiones admitidas, en minúsculas. Es la intersección de lo que el sistema sabe tratar con
     * lo que el tenant tiene habilitado en config: añadir un tipo a la config no lo hace legible
     * por arte de magia, y quitarlo sí lo desactiva.
     *
     * @return list<string>
     */
    public static function tiposAdmitidos(): array
    {
        $configurados = array_map(
            static fn ($t) => mb_strtolower(trim((string) $t)),
            (array) config('importacion.material.tipos', []),
        );

        $conocidos = [...self::TIPOS_HOJA, ...self::TIPOS_DOCUMENTO];

        return array_values(array_intersect($conocidos, $configurados));
    }

    public static function admitido(string $extension): bool
    {
        return in_array(self::normalizar($extension), self::tiposAdmitidos(), true);
    }

    /**
     * Origen del material, o `null` si el tipo no se admite. Devolver `null` en vez de lanzar es
     * deliberado: quien llama tiene que poder redactar el rechazo con `motivoRechazo()`.
     */
    public static function origen(string $extension): ?string
    {
        $extension = self::normalizar($extension);

        if (! self::admitido($extension)) {
            return null;
        }

        return in_array($extension, self::TIPOS_HOJA, true)
            ? self::ORIGEN_HOJA
            : self::ORIGEN_DOCUMENTO;
    }

    public static function esDocumento(string $extension): bool
    {
        return self::origen($extension) === self::ORIGEN_DOCUMENTO;
    }

    /**
     * Mensaje de rechazo comprensible para un tipo no admitido (FR-003). Nunca menciona
     * excepciones, MIME types ni nada que no le sirva a quien lo lee.
     */
    public static function motivoRechazo(string $extension): string
    {
        $extension = self::normalizar($extension);
        $lista = implode(', ', self::tiposAdmitidos());

        if ($extension === '') {
            return "El fichero no tiene extensión y no se puede saber cómo leerlo. Admito: {$lista}.";
        }

        return "No puedo leer ficheros «.{$extension}». Admito: {$lista}.";
    }

    public static function maxPaginas(): int
    {
        return max(1, (int) config('importacion.material.max_paginas', 20));
    }

    private static function normalizar(string $extension): string
    {
        return mb_strtolower(ltrim(trim($extension), '.'));
    }
}
