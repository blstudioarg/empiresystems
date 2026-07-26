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
    case Albaran = 'albaran';
    case Proveedor = 'proveedor';
    case InformeComercial = 'informe_comercial';
    case LogActividad = 'log_actividad';

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
            self::Albaran => 'Albarán',
            self::Proveedor => 'Proveedor',
            self::InformeComercial => 'Informe comercial',
            self::LogActividad => 'Log de actividad',
        };
    }
}
