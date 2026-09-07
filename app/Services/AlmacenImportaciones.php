<?php

namespace App\Services;

use App\Excel\BorradorImportacion;
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

    /**
     * Extensión del material guardado, o `null` si ya no está. Es lo que decide si hay que leerlo
     * como hoja o interpretarlo (feature 046).
     */
    public function extension(string $token): ?string
    {
        $ruta = $this->buscar($token);

        return $ruta === null ? null : mb_strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
    }

    public function borrar(string $token): void
    {
        $ruta = $this->buscar($token);

        if ($ruta !== null) {
            Storage::disk(self::DISCO)->delete($ruta);
        }

        $this->borrarBorrador($token);
    }

    // --- Borrador de importación conversacional (feature 046) ----------------

    /**
     * Guarda la importación en curso junto al material, con el mismo token y la misma caducidad
     * (data-model.md): así `importaciones:purgar`, que barre la carpeta entera por fecha, se lleva
     * también el borrador sin saber que existe (FR-023, FR-024).
     */
    public function guardarBorrador(BorradorImportacion $borrador): void
    {
        Storage::disk(self::DISCO)->put(
            $this->rutaBorrador($borrador->token),
            (string) json_encode($borrador->toArray(), JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * Borrador de una importación, **acotado a empresa y persona** (Principio I + FR-004).
     *
     * Devuelve `null` tanto si no existe como si es de otra persona o de otra empresa: quien
     * pregunta no puede distinguir los tres casos, y por eso el llamador responde 404 y nunca 403
     * —un 403 confirmaría que el token existe—.
     */
    public function borrador(string $token, int $tenantId, int $userId): ?BorradorImportacion
    {
        $ruta = $this->rutaBorrador($token);
        $disco = Storage::disk(self::DISCO);

        if (! $disco->exists($ruta)) {
            return null;
        }

        if ($disco->lastModified($ruta) < now()->subHours(self::HORAS_CADUCIDAD)->timestamp) {
            return null;
        }

        $datos = json_decode((string) $disco->get($ruta), true);

        if (! is_array($datos)) {
            return null;
        }

        $borrador = BorradorImportacion::desdeArray($datos);

        if ($borrador->tenantId !== $tenantId || $borrador->userId !== $userId) {
            return null;
        }

        return $borrador;
    }

    public function borrarBorrador(string $token): void
    {
        Storage::disk(self::DISCO)->delete($this->rutaBorrador($token));
    }

    private function rutaBorrador(string $token): string
    {
        return self::CARPETA."/{$token}.borrador.json";
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
            $nombre = basename($ruta);

            // El borrador comparte token con el material (feature 046) y vive en la misma carpeta:
            // sin esta exclusión, `rutaAbsoluta()` podría devolverlo y el importador intentaría
            // leer un JSON como si fuera una hoja de cálculo.
            if (str_ends_with($nombre, '.borrador.json')) {
                continue;
            }

            if (str_starts_with($nombre, "{$token}.")) {
                return $ruta;
            }
        }

        return null;
    }
}
