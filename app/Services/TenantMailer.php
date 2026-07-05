<?php

namespace App\Services;

use App\Exceptions\EmailNoConfiguradoException;
use App\Support\EmailTenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class TenantMailer
{
    /**
     * @var array{smtp_host: string, smtp_port: string, smtp_encryption: string, smtp_usuario: string,
     *     smtp_password: string, remitente: string, remitente_nombre: string, responder_a: string}
     */
    private array $valores;

    /**
     * @throws EmailNoConfiguradoException
     */
    public function __construct(int $tenantId)
    {
        if (! EmailTenant::estaConfigurado($tenantId)) {
            throw new EmailNoConfiguradoException;
        }

        $this->valores = EmailTenant::valores($tenantId);

        Config::set('mail.mailers.tenant_smtp', [
            'transport' => 'smtp',
            'host' => $this->valores['smtp_host'],
            'port' => (int) $this->valores['smtp_port'],
            'encryption' => $this->valores['smtp_encryption'],
            'username' => $this->valores['smtp_usuario'],
            'password' => $this->valores['smtp_password'],
        ]);
    }

    /**
     * Sin tipo de retorno estricto: en tests, `Mail::fake()`/`Mail::shouldReceive()` devuelven un
     * fake/mock que no implementa necesariamente `Illuminate\Contracts\Mail\Mailer`.
     */
    public function mailer(): mixed
    {
        return Mail::mailer('tenant_smtp');
    }

    public function remitente(): string
    {
        return $this->valores['remitente'];
    }

    public function remitenteNombre(): ?string
    {
        return $this->valores['remitente_nombre'] !== '' ? $this->valores['remitente_nombre'] : null;
    }

    public function responderA(): ?string
    {
        return $this->valores['responder_a'] !== '' ? $this->valores['responder_a'] : null;
    }
}
