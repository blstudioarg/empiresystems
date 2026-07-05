<?php

namespace App\Exceptions;

class EmailNoConfiguradoException extends \DomainException
{
    public function __construct(string $message = 'El correo del tenant no está configurado.')
    {
        parent::__construct($message);
    }
}
