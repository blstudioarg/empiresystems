<?php

namespace App\Enums;

enum TipoEventoFichaje: string
{
    case Entrada = 'entrada';
    case Salida = 'salida';
    case InicioPausa = 'inicio_pausa';
    case FinPausa = 'fin_pausa';

    public function label(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Salida => 'Salida',
            self::InicioPausa => 'Inicio de pausa',
            self::FinPausa => 'Fin de pausa',
        };
    }
}
