<?php

namespace App\Exceptions;

/**
 * El XML Facturae subido no es importable: no es un Facturae válido (esquema roto) o sus importes
 * son incoherentes (FR-016). No se crea ninguna compra.
 */
class FacturaeImportacionException extends \DomainException
{
    public function __construct(string $message = 'El archivo no es un Facturae válido.')
    {
        parent::__construct($message);
    }
}
