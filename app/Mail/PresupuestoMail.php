<?php

namespace App\Mail;

use App\Models\Presupuesto;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PresupuestoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Presupuesto $presupuesto,
        private readonly string $pdf,
    ) {}

    public function build(): self
    {
        return $this->subject('Presupuesto '.$this->presupuesto->numero.' de '.$this->presupuesto->tenant->nombre_comercial)
            ->view('emails.presupuesto', ['presupuesto' => $this->presupuesto])
            ->attachData($this->pdf, $this->presupuesto->numero.'.pdf', ['mime' => 'application/pdf']);
    }
}
