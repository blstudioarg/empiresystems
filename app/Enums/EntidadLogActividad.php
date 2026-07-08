<?php

namespace App\Enums;

enum EntidadLogActividad: string
{
    case Cliente = 'cliente';
    case Articulo = 'articulo';
    case Factura = 'factura';
    case Configuracion = 'configuracion';
    case Usuario = 'usuario';
    case Lead = 'lead';
    case Oportunidad = 'oportunidad';
    case Presupuesto = 'presupuesto';

    public function label(): string
    {
        return match ($this) {
            self::Cliente => 'Cliente',
            self::Articulo => 'Artículo',
            self::Factura => 'Factura',
            self::Configuracion => 'Configuración',
            self::Usuario => 'Usuario',
            self::Lead => 'Lead',
            self::Oportunidad => 'Oportunidad',
            self::Presupuesto => 'Presupuesto',
        };
    }
}
