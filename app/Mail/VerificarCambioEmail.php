<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificarCambioEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $urlVerificacion,
    ) {}

    public function build(): self
    {
        return $this->subject('Confirmá tu nuevo correo')
            ->view('emails.verificar-cambio-email');
    }
}
