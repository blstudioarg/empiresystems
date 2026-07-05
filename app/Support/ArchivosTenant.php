<?php

namespace App\Support;

use App\Models\Configuracion;

class ArchivosTenant
{
    public const CLAVE_LIMITE_MB = 'archivos.limite_mb';

    public const CLAVE_TIPOS_PERMITIDOS = 'archivos.tipos_permitidos';

    public const DEFAULT_LIMITE_MB = 10;

    /**
     * Lista blanca de extensiones permitidas (FR-016b), agrupadas solo a efectos de lectura.
     *
     * @var array<int, string>
     */
    public const EXTENSIONES_PERMITIDAS = [
        'pdf',
        'jpg', 'jpeg', 'png', 'webp', 'gif',
        'docx', 'xlsx', 'pptx',
        'odt', 'ods', 'odp',
        'txt', 'csv',
    ];

    /**
     * MIME reales aceptados (validados contra el contenido, no solo la extensión).
     *
     * @var array<int, string>
     */
    public const MIMES_PERMITIDOS = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'text/plain',
        'text/csv',
    ];

    /**
     * Extensiones con preview inline soportado (FR-016): pdf e imágenes.
     *
     * @var array<int, string>
     */
    public const EXTENSIONES_CON_PREVIEW = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'];

    public static function limiteMb(int $tenantId): int
    {
        $valor = Configuracion::query()
            ->where('tenant_id', $tenantId)
            ->where('clave', self::CLAVE_LIMITE_MB)
            ->value('valor');

        return $valor !== null ? (int) $valor : self::DEFAULT_LIMITE_MB;
    }

    public static function limiteKb(int $tenantId): int
    {
        return self::limiteMb($tenantId) * 1024;
    }

    public static function tienePreview(string $extension): bool
    {
        return in_array(strtolower($extension), self::EXTENSIONES_CON_PREVIEW, true);
    }
}
