<?php

namespace App\Support;

use App\Models\Configuracion;
use Illuminate\Support\Facades\Crypt;

class EmailTenant
{
    public const CLAVE_SMTP_HOST = 'email.smtp_host';

    public const CLAVE_SMTP_PORT = 'email.smtp_port';

    public const CLAVE_SMTP_ENCRYPTION = 'email.smtp_encryption';

    public const CLAVE_SMTP_USUARIO = 'email.smtp_usuario';

    public const CLAVE_SMTP_PASSWORD = 'email.smtp_password';

    public const CLAVE_REMITENTE = 'email.remitente';

    public const CLAVE_REMITENTE_NOMBRE = 'email.remitente_nombre';

    public const CLAVE_RESPONDER_A = 'email.responder_a';

    public const DEFAULT_SMTP_HOST = '';

    public const DEFAULT_SMTP_PORT = '465';

    public const DEFAULT_SMTP_ENCRYPTION = 'ssl';

    public const DEFAULT_SMTP_USUARIO = '';

    public const DEFAULT_SMTP_PASSWORD = '';

    public const DEFAULT_REMITENTE = '';

    public const DEFAULT_REMITENTE_NOMBRE = '';

    public const DEFAULT_RESPONDER_A = '';

    /**
     * Valores efectivos de la config de email del tenant, ya descifrados. La contraseña nunca
     * debe volver al front en claro: quien llame a este método es exclusivamente backend
     * (TenantMailer, comprobación de completitud).
     *
     * @return array{smtp_host: string, smtp_port: string, smtp_encryption: string, smtp_usuario: string,
     *     smtp_password: string, remitente: string, remitente_nombre: string, responder_a: string}
     */
    public static function valores(int $tenantId): array
    {
        $configuraciones = Configuracion::query()
            ->where('tenant_id', $tenantId)
            ->where('grupo', 'email')
            ->pluck('valor', 'clave');

        $passwordCifrada = $configuraciones->get(self::CLAVE_SMTP_PASSWORD);

        return [
            'smtp_host' => $configuraciones->get(self::CLAVE_SMTP_HOST, self::DEFAULT_SMTP_HOST),
            'smtp_port' => $configuraciones->get(self::CLAVE_SMTP_PORT, self::DEFAULT_SMTP_PORT),
            'smtp_encryption' => $configuraciones->get(self::CLAVE_SMTP_ENCRYPTION, self::DEFAULT_SMTP_ENCRYPTION),
            'smtp_usuario' => $configuraciones->get(self::CLAVE_SMTP_USUARIO, self::DEFAULT_SMTP_USUARIO),
            'smtp_password' => $passwordCifrada ? Crypt::decryptString($passwordCifrada) : self::DEFAULT_SMTP_PASSWORD,
            'remitente' => $configuraciones->get(self::CLAVE_REMITENTE, self::DEFAULT_REMITENTE),
            'remitente_nombre' => $configuraciones->get(self::CLAVE_REMITENTE_NOMBRE, self::DEFAULT_REMITENTE_NOMBRE),
            'responder_a' => $configuraciones->get(self::CLAVE_RESPONDER_A, self::DEFAULT_RESPONDER_A),
        ];
    }

    public static function estaConfigurado(int $tenantId): bool
    {
        $valores = self::valores($tenantId);

        return $valores['smtp_host'] !== ''
            && $valores['smtp_port'] !== ''
            && $valores['smtp_encryption'] !== ''
            && $valores['smtp_usuario'] !== ''
            && $valores['smtp_password'] !== ''
            && $valores['remitente'] !== '';
    }

    public static function tienePasswordGuardada(int $tenantId): bool
    {
        return Configuracion::query()
            ->where('tenant_id', $tenantId)
            ->where('clave', self::CLAVE_SMTP_PASSWORD)
            ->exists();
    }
}
