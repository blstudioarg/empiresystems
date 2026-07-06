<?php

namespace App\Exceptions;

/**
 * No se puede generar el Facturae de una factura: no está emitida, el tenant no tiene certificado
 * válido, o el NIF del emisor/receptor es inválido (FR-007/008/021).
 */
class FacturaeNoGenerableException extends \DomainException
{
    public function __construct(string $message = 'No se puede generar el Facturae.')
    {
        parent::__construct($message);
    }
}
