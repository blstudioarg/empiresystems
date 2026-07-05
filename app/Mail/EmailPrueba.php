<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailPrueba extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(private readonly string $nombreTenant) {}

    public function build(): self
    {
        return $this->subject("Email de prueba de {$this->nombreTenant}")
            ->html("Este es un email de prueba de {$this->nombreTenant}. Si lo has recibido, tu configuración de correo es correcta.");
    }
}
