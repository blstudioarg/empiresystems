<?php

namespace App\Support;

/**
 * Cálculo puro de la huella o «hash» de los registros de facturación Verifactu (SHA-256), según
 * la especificación oficial de la AEAT ("Detalle de las especificaciones técnicas para generación
 * de la huella o hash de los registros de facturación", v0.1.2, 27/08/2024 — docs/02 §1.1). Sin
 * dependencias de base de datos ni de Eloquent: solo primitivos, para poder testear contra los
 * vectores de prueba oficiales.
 */
class HuellaVerifactu
{
    /**
     * Huella del registro de alta. Orden de campos fijado por la AEAT (no reordenable):
     * IDEmisorFactura, NumSerieFactura, FechaExpedicionFactura, TipoFactura, CuotaTotal,
     * ImporteTotal, Huella (anterior), FechaHoraHusoGenRegistro.
     *
     * @param  array{idEmisorFactura: string, numSerieFactura: string, fechaExpedicionFactura: string, tipoFactura: string, cuotaTotal: string, importeTotal: string, fechaHoraHusoGenRegistro: string}  $campos
     * @param  string  $huellaAnterior  Huella del registro anterior de la cadena del tenant; cadena vacía si es el primero.
     */
    public static function alta(array $campos, string $huellaAnterior): string
    {
        $cadena =
            self::campo('IDEmisorFactura', $campos['idEmisorFactura'])
            .self::campo('NumSerieFactura', $campos['numSerieFactura'])
            .self::campo('FechaExpedicionFactura', $campos['fechaExpedicionFactura'])
            .self::campo('TipoFactura', $campos['tipoFactura'])
            .self::campo('CuotaTotal', $campos['cuotaTotal'])
            .self::campo('ImporteTotal', $campos['importeTotal'])
            .self::campo('Huella', $huellaAnterior)
            .self::campo('FechaHoraHusoGenRegistro', $campos['fechaHoraHusoGenRegistro'], separador: false);

        return self::hash($cadena);
    }

    /**
     * Huella del registro de anulación. Orden de campos: IDEmisorFacturaAnulada,
     * NumSerieFacturaAnulada, FechaExpedicionFacturaAnulada, Huella (anterior),
     * FechaHoraHusoGenRegistro.
     *
     * @param  array{idEmisorFacturaAnulada: string, numSerieFacturaAnulada: string, fechaExpedicionFacturaAnulada: string, fechaHoraHusoGenRegistro: string}  $campos
     */
    public static function anulacion(array $campos, string $huellaAnterior): string
    {
        $cadena =
            self::campo('IDEmisorFacturaAnulada', $campos['idEmisorFacturaAnulada'])
            .self::campo('NumSerieFacturaAnulada', $campos['numSerieFacturaAnulada'])
            .self::campo('FechaExpedicionFacturaAnulada', $campos['fechaExpedicionFacturaAnulada'])
            .self::campo('Huella', $huellaAnterior)
            .self::campo('FechaHoraHusoGenRegistro', $campos['fechaHoraHusoGenRegistro'], separador: false);

        return self::hash($cadena);
    }

    /**
     * `nombreCampo=valor&` (o sin `&` final si `separador` es falso). Si el valor es vacío se deja
     * igualmente `nombreCampo=` sin nada a continuación, tal y como exige la especificación para el
     * primer eslabón de la cadena (sin huella anterior).
     */
    private static function campo(string $nombre, string $valor, bool $separador = true): string
    {
        $campo = $nombre.'='.trim($valor);

        return $separador ? $campo.'&' : $campo;
    }

    /**
     * SHA-256 sobre la cadena codificada en UTF-8, en hexadecimal y mayúsculas (formato de salida
     * fijado por la AEAT).
     */
    private static function hash(string $cadena): string
    {
        return strtoupper(hash('sha256', mb_convert_encoding($cadena, 'UTF-8'), binary: false));
    }
}
