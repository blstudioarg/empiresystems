<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda/recupera/borra el fichero de una importación mientras dura el paso de previsualización
 * (research.md D2). Almacenamiento privado y transitorio: nunca accesible por URL pública, borrado
 * al confirmar/cancelar, y purgado a las 24 h por `importaciones:purgar` (Principio II).
 */
class AlmacenImportaciones
{
    private const DISCO = 'local';

    private const CARPETA = 'importaciones';

    private const HORAS_CADUCIDAD = 24;

    public function guardar(UploadedFile $fichero): string
    {
        $token = (string) Str::uuid();
        $extension = $fichero->getClientOriginalExtension() ?: 'xlsx';

        Storage::disk(self::DISCO)->putFileAs(self::CARPETA, $fichero, "{$token}.{$extension}");

        return $token;
    }

    /**
     * Ruta absoluta del fichero, o `null` si no existe o ya caducó (>24 h) — aunque el comando de
     * purga todavía no haya pasado a borrarlo físicamente.
     */
    public function rutaAbsoluta(string $token): ?string
    {
        $ruta = $this->buscar($token);

        if ($ruta === null) {
            return null;
        }

        if (Storage::disk(self::DISCO)->lastModified($ruta) < now()->subHours(self::HORAS_CADUCIDAD)->timestamp) {
            return null;
        }

        return Storage::disk(self::DISCO)->path($ruta);
    }

    public function borrar(string $token): void
    {
        $ruta = $this->buscar($token);

        if ($ruta !== null) {
            Storage::disk(self::DISCO)->delete($ruta);
        }
    }

    /**
     * Barre los ficheros de más de 24 h (huérfanos de previsualizaciones nunca confirmadas ni
     * canceladas). Devuelve cuántos borró.
     */
    public function purgarHuerfanos(): int
    {
        $limite = now()->subHours(self::HORAS_CADUCIDAD)->timestamp;
        $borrados = 0;

        foreach (Storage::disk(self::DISCO)->files(self::CARPETA) as $ruta) {
            if (Storage::disk(self::DISCO)->lastModified($ruta) < $limite) {
                Storage::disk(self::DISCO)->delete($ruta);
                $borrados++;
            }
        }

        return $borrados;
    }

    private function buscar(string $token): ?string
    {
        foreach (Storage::disk(self::DISCO)->files(self::CARPETA) as $ruta) {
            if (str_starts_with(basename($ruta), "{$token}.")) {
                return $ruta;
            }
        }

        return null;
    }
}
