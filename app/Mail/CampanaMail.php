<?php

namespace App\Mail;

use App\Models\Campana;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CampanaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Campana $campana,
    ) {}

    public function build(): self
    {
        return $this->subject($this->campana->asunto)
            ->view('emails.campana', ['campana' => $this->campana]);
    }
}
