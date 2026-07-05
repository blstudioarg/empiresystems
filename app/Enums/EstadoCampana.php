<?php

namespace App\Enums;

enum EstadoCampana: string
{
    case Borrador = 'borrador';
    case EnCurso = 'en_curso';
    case Finalizada = 'finalizada';
}
