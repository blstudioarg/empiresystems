<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda/recupera/borra el documento de una propuesta de compra mientras el usuario la revisa
 * (feature 044). Mismo patrón que `AlmacenImportaciones`, con una divergencia deliberada: la
 * carpeta se segmenta por tenant y toda resolución ocurre **solo** dentro del subárbol del tenant
 * activo, de modo que un token de otro tenant simplemente "no existe" (Principio I).
 *
 * El documento lleva datos personales del proveedor: retención acotada y purga diaria vía
 * `compras-documentos:purgar` (Principio II, RGPD).
 */
class AlmacenDocumentosCompra
{
    private const DISCO = 'local';

    private const CARPETA = 'compras-documentos';

    public function guardar(UploadedFile $fichero): string
    {
        $token = (string) Str::uuid();
        $extension = strtolower($fichero->getClientOriginalExtension() ?: 'pdf');

        Storage::disk(self::DISCO)->putFileAs($this->carpetaTenant(), $fichero, "{$token}.{$extension}");

        return $token;
    }

    /**
     * Ruta absoluta del fichero, o `null` si no existe, no es del tenant activo, o ya caducó
     * —aunque la purga todavía no haya pasado a borrarlo físicamente—.
     */
    public function rutaAbsoluta(string $token): ?string
    {
        $ruta = $this->buscar($token);

        if ($ruta === null) {
            return null;
        }

        if (Storage::disk(self::DISCO)->lastModified($ruta) < $this->limiteCaducidad()) {
            return null;
        }

        return Storage::disk(self::DISCO)->path($ruta);
    }

    /**
     * Extensión del fichero del token (sin punto), o `null` si no es resoluble.
     */
    public function extension(string $token): ?string
    {
        $ruta = $this->buscar($token);

        return $ruta === null ? null : strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
    }

    /**
     * Idempotente: un token ya borrado (o ajeno) no es un error.
     */
    public function borrar(string $token): void
    {
        $ruta = $this->buscar($token);

        if ($ruta !== null) {
            Storage::disk(self::DISCO)->delete($ruta);
        }
    }

    /**
     * Barre los documentos caducados de **todos** los tenants (lo ejecuta el comando programado,
     * fuera de contexto de tenant). Devuelve cuántos borró.
     */
    public function purgarHuerfanos(): int
    {
        $limite = $this->limiteCaducidad();
        $borrados = 0;

        foreach (Storage::disk(self::DISCO)->allFiles(self::CARPETA) as $ruta) {
            if (Storage::disk(self::DISCO)->lastModified($ruta) < $limite) {
                Storage::disk(self::DISCO)->delete($ruta);
                $borrados++;
            }
        }

        return $borrados;
    }

    /**
     * Busca **solo** dentro del subárbol del tenant activo: un token del tenant A es indistinguible
     * de uno inexistente desde el tenant B.
     */
    private function buscar(string $token): ?string
    {
        // Un token manipulado no puede escaparse de la carpeta del tenant.
        if (! Str::isUuid($token)) {
            return null;
        }

        foreach (Storage::disk(self::DISCO)->files($this->carpetaTenant()) as $ruta) {
            if (str_starts_with(basename($ruta), "{$token}.")) {
                return $ruta;
            }
        }

        return null;
    }

    private function carpetaTenant(): string
    {
        return self::CARPETA.'/'.tenant()->getTenantKey();
    }

    private function limiteCaducidad(): int
    {
        return now()->subHours((int) config('compras.documentos.horas_retencion', 24))->timestamp;
    }
}
