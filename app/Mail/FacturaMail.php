<?php

namespace App\Mail;

use App\Models\Factura;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FacturaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Factura $factura,
        private readonly string $pdf,
    ) {}

    public function build(): self
    {
        $nombreArchivo = ($this->factura->numero_completo ?? 'factura-'.$this->factura->id).'.pdf';

        return $this->subject('Factura '.($this->factura->numero_completo ?? '').' de '.$this->factura->tenant->nombre_comercial)
            ->view('emails.factura', ['factura' => $this->factura])
            ->attachData($this->pdf, $nombreArchivo, ['mime' => 'application/pdf']);
    }
}
