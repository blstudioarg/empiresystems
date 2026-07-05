<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AlmacenArchivos
{
    /**
     * Único punto de escritura física de un documento: guarda el fichero en el disco privado
     * `documentos`, particionado por tenant, con un nombre físico UUID independiente del nombre
     * visible (D2/FR-020).
     *
     * @return array{ruta: string, mime: string, extension: string, tamano: int, nombre_original: string}
     */
    public function guardar(UploadedFile $archivo, int $tenantId): array
    {
        $extension = strtolower($archivo->getClientOriginalExtension());
        $carpeta = "tenants/{$tenantId}/documentos";
        $nombreFisico = Str::uuid()->toString().'.'.$extension;

        $rutaGuardada = $archivo->storeAs($carpeta, $nombreFisico, 'documentos');

        if ($rutaGuardada === false) {
            throw new RuntimeException('No se pudo guardar el fichero en el disco privado.');
        }

        return [
            'ruta' => $rutaGuardada,
            'mime' => $archivo->getMimeType(),
            'extension' => $extension,
            'tamano' => $archivo->getSize(),
            'nombre_original' => $archivo->getClientOriginalName(),
        ];
    }

    public function borrar(string $ruta): void
    {
        Storage::disk('documentos')->delete($ruta);
    }
}
