<?php

namespace App\Support;

use App\Exceptions\CertificadoInvalidoException;
use App\Models\Configuracion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * Gestión del certificado PKCS#12 del tenant para firmar Facturae (XAdES-EPES). El `.p12` vive en
 * el disco privado `documentos` (particionado por tenant); la password se guarda cifrada en
 * `configuraciones` (grupo `certificado`), replicando el patrón de `EmailTenant`. Uno vigente por
 * tenant (data-model §3).
 */
class CertificadoTenant
{
    public const CLAVE_ARCHIVO_PATH = 'certificado.archivo_path';

    public const CLAVE_PASSWORD = 'certificado.password';

    public const CLAVE_TITULAR = 'certificado.titular';

    public const CLAVE_CADUCA_AT = 'certificado.caduca_at';

    public static function existe(int $tenantId): bool
    {
        return Configuracion::query()
            ->where('tenant_id', $tenantId)
            ->where('clave', self::CLAVE_ARCHIVO_PATH)
            ->exists();
    }

    /**
     * Valida el `.p12` subido (password abre el archivo, hay clave privada, no está caducado),
     * lo guarda en disco privado y persiste sus metadatos. Sobrescribe el certificado anterior.
     *
     * @throws CertificadoInvalidoException
     */
    public static function guardar(UploadedFile $archivo, string $password, int $tenantId): void
    {
        $contenido = file_get_contents($archivo->getRealPath());

        if (! openssl_pkcs12_read($contenido, $datos, $password)) {
            throw new CertificadoInvalidoException('La contraseña no es correcta o el archivo no es un certificado .p12/.pfx válido.');
        }

        $clavePrivada = openssl_pkey_get_private($datos['pkey']);

        if ($clavePrivada === false) {
            throw new CertificadoInvalidoException('El certificado no contiene una clave privada utilizable.');
        }

        $info = openssl_x509_parse($datos['cert']);

        if ($info === false) {
            throw new CertificadoInvalidoException('No se pudo leer el certificado X.509.');
        }

        $caducaAt = $info['validTo_time_t'] ?? null;

        if ($caducaAt !== null && $caducaAt < now()->timestamp) {
            throw new CertificadoInvalidoException('El certificado está caducado.');
        }

        $anterior = self::rutaArchivo($tenantId);

        if ($anterior) {
            Storage::disk('documentos')->delete($anterior);
        }

        $extension = strtolower($archivo->getClientOriginalExtension()) ?: 'p12';
        $ruta = "tenants/{$tenantId}/certificado/certificado.{$extension}";
        Storage::disk('documentos')->put($ruta, $contenido);

        $titular = $info['subject']['CN'] ?? $info['subject']['O'] ?? 'Sin titular';
        $caducaFecha = $caducaAt !== null ? date('Y-m-d', $caducaAt) : null;

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => self::CLAVE_ARCHIVO_PATH],
            ['valor' => $ruta, 'tipo' => 'string', 'grupo' => 'certificado']
        );

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => self::CLAVE_PASSWORD],
            ['valor' => Crypt::encryptString($password), 'tipo' => 'string', 'grupo' => 'certificado']
        );

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => self::CLAVE_TITULAR],
            ['valor' => $titular, 'tipo' => 'string', 'grupo' => 'certificado']
        );

        Configuracion::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'clave' => self::CLAVE_CADUCA_AT],
            ['valor' => $caducaFecha, 'tipo' => 'string', 'grupo' => 'certificado']
        );
    }

    /**
     * Contenido binario del `.p12` y su password descifrada, listos para firmar. Uso exclusivo
     * de backend (GeneradorFacturae); la password nunca vuelve al front.
     *
     * @throws CertificadoInvalidoException
     */
    public static function paraFirmar(int $tenantId): array
    {
        $ruta = self::rutaArchivo($tenantId);
        $passwordCifrada = self::valor($tenantId, self::CLAVE_PASSWORD);

        if ($ruta === null || $passwordCifrada === null) {
            throw new CertificadoInvalidoException('El tenant no tiene un certificado configurado.');
        }

        if (! Storage::disk('documentos')->exists($ruta)) {
            throw new CertificadoInvalidoException('El certificado configurado no se encuentra en disco.');
        }

        $contenido = Storage::disk('documentos')->get($ruta);
        $password = Crypt::decryptString($passwordCifrada);

        if (! openssl_pkcs12_read($contenido, $datos, $password)) {
            throw new CertificadoInvalidoException('El certificado guardado ya no es válido.');
        }

        $info = openssl_x509_parse($datos['cert']);
        $caducaAt = $info['validTo_time_t'] ?? null;

        if ($caducaAt !== null && $caducaAt < now()->timestamp) {
            throw new CertificadoInvalidoException('El certificado ha caducado. Sube uno nuevo antes de generar el Facturae.');
        }

        return ['contenido' => $contenido, 'password' => $password];
    }

    /**
     * Metadatos para mostrar en la vista de configuración (titular/caducidad/estado), sin exponer
     * jamás la password.
     */
    public static function metadatos(int $tenantId): ?array
    {
        if (! self::existe($tenantId)) {
            return null;
        }

        $caducaAt = self::valor($tenantId, self::CLAVE_CADUCA_AT);

        return [
            'titular' => self::valor($tenantId, self::CLAVE_TITULAR),
            'caduca_at' => $caducaAt,
            'caducado' => $caducaAt !== null && strtotime($caducaAt) < now()->timestamp,
        ];
    }

    private static function rutaArchivo(int $tenantId): ?string
    {
        return self::valor($tenantId, self::CLAVE_ARCHIVO_PATH);
    }

    private static function valor(int $tenantId, string $clave): ?string
    {
        return Configuracion::query()
            ->where('tenant_id', $tenantId)
            ->where('clave', $clave)
            ->value('valor');
    }
}
