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
        private readonly ?string $facturaeXml = null,
    ) {}

    public function build(): self
    {
        $nombreBase = $this->factura->numero_completo ?? 'factura-'.$this->factura->id;

        $mailable = $this->subject('Factura '.($this->factura->numero_completo ?? '').' de '.$this->factura->tenant->nombre_comercial)
            ->view('emails.factura', ['factura' => $this->factura])
            ->attachData($this->pdf, $nombreBase.'.pdf', ['mime' => 'application/pdf']);

        if ($this->facturaeXml !== null) {
            $mailable->attachData($this->facturaeXml, $nombreBase.'.xsig', ['mime' => 'application/xml']);
        }

        return $mailable;
    }
}
