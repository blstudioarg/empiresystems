<?php

namespace App\Exceptions;

/**
 * El certificado PKCS#12 del tenant no es utilizable: contraseña incorrecta, sin clave privada,
 * ilegible o caducado. Bloquea la firma/generación de Facturae (FR-007/FR-011).
 */
class CertificadoInvalidoException extends \DomainException
{
    public function __construct(string $message = 'El certificado no es válido.')
    {
        parent::__construct($message);
    }
}
