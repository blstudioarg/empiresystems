<?php

namespace App\Services;

use App\Enums\CalificacionOperacion;
use App\Enums\RegimenImpositivo;
use App\Enums\TipoFactura;
use App\Models\Factura;
use App\Support\ConfigTenant;
use Illuminate\Support\Collection;

/**
 * Mapea una factura emitida (+ líneas + tenant) a los campos de entrada de la huella Verifactu y al
 * XML del registro de facturación (Orden HAC/1177/2024, docs/02-facturacion-espana.md §1). Server-
 * side y agnóstico al régimen (IVA/IGIC/IPSI): ningún importe se recalcula, se leen los ya
 * congelados en la factura (Principio III). No toca base de datos ni genera huella — eso lo hace
 * `RegistroVerifactu` con `HuellaVerifactu`.
 */
class GeneradorXmlVerifactu
{
    /**
     * Campos primitivos (formato AEAT) para calcular la huella del registro de alta con
     * `HuellaVerifactu::alta()`.
     *
     * @return array{idEmisorFactura: string, numSerieFactura: string, fechaExpedicionFactura: string, tipoFactura: string, cuotaTotal: string, importeTotal: string, fechaHoraHusoGenRegistro: string}
     */
    public function camposAlta(Factura $factura): array
    {
        return [
            'idEmisorFactura' => (string) $factura->tenant->nif,
            'numSerieFactura' => (string) $factura->numero_completo,
            'fechaExpedicionFactura' => $factura->fecha_expedicion->format('d-m-Y'),
            'tipoFactura' => $this->tipoFacturaVerifactu($factura),
            'cuotaTotal' => $this->importe($this->cuotaTotal($factura)),
            'importeTotal' => $this->importe((float) $factura->total),
            'fechaHoraHusoGenRegistro' => $this->fechaHoraHusoGenRegistro($factura->tenant_id),
        ];
    }

    /**
     * @return array{idEmisorFacturaAnulada: string, numSerieFacturaAnulada: string, fechaExpedicionFacturaAnulada: string, fechaHoraHusoGenRegistro: string}
     */
    public function camposAnulacion(Factura $factura): array
    {
        return [
            'idEmisorFacturaAnulada' => (string) $factura->tenant->nif,
            'numSerieFacturaAnulada' => (string) $factura->numero_completo,
            'fechaExpedicionFacturaAnulada' => $factura->fecha_expedicion->format('d-m-Y'),
            'fechaHoraHusoGenRegistro' => $this->fechaHoraHusoGenRegistro($factura->tenant_id),
        ];
    }

    /**
     * XML del registro de alta (RegistroAlta), con la huella ya calculada incluida.
     */
    public function xmlAlta(Factura $factura, string $huella, string $huellaAnterior): string
    {
        $campos = $this->camposAlta($factura);

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $registro = $doc->createElement('RegistroAlta');
        $doc->appendChild($registro);

        $this->append($doc, $registro, 'IDVersion', '1.0');

        $idFactura = $doc->createElement('IDFactura');
        $registro->appendChild($idFactura);
        $this->append($doc, $idFactura, 'IDEmisorFactura', $campos['idEmisorFactura']);
        $this->append($doc, $idFactura, 'NumSerieFactura', $campos['numSerieFactura']);
        $this->append($doc, $idFactura, 'FechaExpedicionFactura', $campos['fechaExpedicionFactura']);

        $this->append($doc, $registro, 'NombreRazonEmisor', $factura->tenant->razon_social ?: $factura->tenant->nombre_comercial);
        $this->append($doc, $registro, 'TipoFactura', $campos['tipoFactura']);

        if ($factura->es_rectificativa && $factura->facturaRectificada) {
            $facturasRectificadas = $doc->createElement('FacturasRectificadas');
            $registro->appendChild($facturasRectificadas);
            $idRectificada = $doc->createElement('IDFacturaRectificada');
            $facturasRectificadas->appendChild($idRectificada);
            $this->append($doc, $idRectificada, 'IDEmisorFactura', $campos['idEmisorFactura']);
            $this->append($doc, $idRectificada, 'NumSerieFactura', (string) $factura->facturaRectificada->numero_completo);
            $this->append($doc, $idRectificada, 'FechaExpedicionFactura', $factura->facturaRectificada->fecha_expedicion->format('d-m-Y'));
        }

        $this->append($doc, $registro, 'DescripcionOperacion', $this->descripcionOperacion($factura));

        if ($factura->cliente_nif) {
            $destinatarios = $doc->createElement('Destinatarios');
            $registro->appendChild($destinatarios);
            $idDestinatario = $doc->createElement('IDDestinatario');
            $destinatarios->appendChild($idDestinatario);
            $this->append($doc, $idDestinatario, 'NombreRazon', $factura->cliente_razon_social ?: $factura->cliente_nombre);
            $this->append($doc, $idDestinatario, 'NIF', $factura->cliente_nif);
        }

        $desglose = $doc->createElement('Desglose');
        $registro->appendChild($desglose);

        foreach ($this->desglose($factura) as $detalle) {
            $detalleEl = $doc->createElement('DetalleDesglose');
            $desglose->appendChild($detalleEl);

            $this->append($doc, $detalleEl, 'Impuesto', $this->codigoImpuesto($factura->regimen_impositivo));
            $this->append($doc, $detalleEl, 'ClaveRegimen', '01');

            if ($detalle['causaExencion'] !== null) {
                $this->append($doc, $detalleEl, 'OperacionExenta', $detalle['causaExencion']);
            } else {
                $this->append($doc, $detalleEl, 'CalificacionOperacion', $detalle['calificacionOperacion']);
            }

            if ($detalle['tipoImpositivo'] !== null) {
                $this->append($doc, $detalleEl, 'TipoImpositivo', $this->importe($detalle['tipoImpositivo']));
            }

            $this->append($doc, $detalleEl, 'BaseImponibleOimporteNoSujeto', $this->importe($detalle['base']));

            if ($detalle['cuotaRepercutida'] !== null) {
                $this->append($doc, $detalleEl, 'CuotaRepercutida', $this->importe($detalle['cuotaRepercutida']));
            }
        }

        $this->append($doc, $registro, 'CuotaTotal', $campos['cuotaTotal']);
        $this->append($doc, $registro, 'ImporteTotal', $campos['importeTotal']);

        $encadenamiento = $doc->createElement('Encadenamiento');
        $registro->appendChild($encadenamiento);

        if ($huellaAnterior === '') {
            $this->append($doc, $encadenamiento, 'PrimerRegistro', 'S');
        } else {
            $anterior = $doc->createElement('RegistroAnterior');
            $encadenamiento->appendChild($anterior);
            // El registro anterior de la cadena es siempre del mismo emisor (encadenamiento
            // por tenant); no se persiste su serie/fecha aparte, la huella ya lo identifica.
            $this->append($doc, $anterior, 'IDEmisorFactura', $campos['idEmisorFactura']);
            $this->append($doc, $anterior, 'Huella', $huellaAnterior);
        }

        $registro->appendChild($this->sistemaInformatico($doc, $factura->tenant_id));

        $this->append($doc, $registro, 'FechaHoraHusoGenRegistro', $campos['fechaHoraHusoGenRegistro']);
        $this->append($doc, $registro, 'TipoHuella', '01');
        $this->append($doc, $registro, 'Huella', $huella);

        return $doc->saveXML();
    }

    /**
     * XML del registro de anulación (RegistroAnulacion).
     */
    public function xmlAnulacion(Factura $factura, string $huella, string $huellaAnterior, string $motivo): string
    {
        $campos = $this->camposAnulacion($factura);

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $registro = $doc->createElement('RegistroAnulacion');
        $doc->appendChild($registro);

        $this->append($doc, $registro, 'IDVersion', '1.0');

        $idFactura = $doc->createElement('IDFactura');
        $registro->appendChild($idFactura);
        $this->append($doc, $idFactura, 'IDEmisorFacturaAnulada', $campos['idEmisorFacturaAnulada']);
        $this->append($doc, $idFactura, 'NumSerieFacturaAnulada', $campos['numSerieFacturaAnulada']);
        $this->append($doc, $idFactura, 'FechaExpedicionFacturaAnulada', $campos['fechaExpedicionFacturaAnulada']);

        $this->append($doc, $registro, 'Motivo', $motivo);

        $encadenamiento = $doc->createElement('Encadenamiento');
        $registro->appendChild($encadenamiento);

        if ($huellaAnterior === '') {
            $this->append($doc, $encadenamiento, 'PrimerRegistro', 'S');
        } else {
            $anterior = $doc->createElement('RegistroAnterior');
            $encadenamiento->appendChild($anterior);
            $this->append($doc, $anterior, 'IDEmisorFactura', $campos['idEmisorFacturaAnulada']);
            $this->append($doc, $anterior, 'Huella', $huellaAnterior);
        }

        $registro->appendChild($this->sistemaInformatico($doc, $factura->tenant_id));

        $this->append($doc, $registro, 'FechaHoraHusoGenRegistro', $campos['fechaHoraHusoGenRegistro']);
        $this->append($doc, $registro, 'TipoHuella', '01');
        $this->append($doc, $registro, 'Huella', $huella);

        return $doc->saveXML();
    }

    /**
     * TipoFactura Verifactu (ClaveTipoFacturaType, docs/02 §1.1): F1 ordinaria, F2 simplificada,
     * R1/R5 rectificativa (R5 si rectifica una simplificada, R1 en el resto — el modelo actual no
     * distingue los motivos legales art. 80 Uno/Dos/Tres/Cuatro de una R1..R4, así que se usa R1
     * como motivo genérico; decisión documentada aquí, no en un vector oficial).
     */
    private function tipoFacturaVerifactu(Factura $factura): string
    {
        if ($factura->es_rectificativa) {
            $original = $factura->facturaRectificada;

            return ($original && $original->tipo === TipoFactura::Simplificada) ? 'R5' : 'R1';
        }

        return $factura->tipo === TipoFactura::Simplificada ? 'F2' : 'F1';
    }

    /**
     * Suma de cuotas repercutidas (impuesto + recargo de equivalencia). El IRPF es una retención,
     * no una cuota repercutida, y no participa en `CuotaTotal`.
     */
    private function cuotaTotal(Factura $factura): float
    {
        return round((float) $factura->cuota_impuesto_total + (float) $factura->cuota_recargo_total, 2);
    }

    private function descripcionOperacion(Factura $factura): string
    {
        $conceptos = $factura->lineas->pluck('concepto')->filter()->unique()->implode('; ');

        return $conceptos !== '' ? mb_substr($conceptos, 0, 500) : 'Venta de bienes/prestación de servicios';
    }

    /**
     * Agrega las líneas por (calificación de operación | causa de exención) + tipo impositivo,
     * que es el nivel al que Verifactu exige el desglose (docs/02 §6) — más fino que
     * `factura_impuestos` (que solo agrupa por tipo_impuesto+porcentaje, sin calificación).
     *
     * @return list<array{calificacionOperacion: ?string, causaExencion: ?string, tipoImpositivo: ?float, base: float, cuotaRepercutida: ?float}>
     */
    private function desglose(Factura $factura): array
    {
        /** @var Collection $grupos */
        $grupos = $factura->lineas->groupBy(function ($linea) {
            $causa = $linea->causa_exencion?->value ?? '';
            $calificacion = $linea->calificacion_operacion?->value ?? CalificacionOperacion::S1->value;
            $tipo = $causa !== '' ? '' : (string) $linea->tipo_impositivo;

            return $causa.'|'.$calificacion.'|'.$tipo;
        });

        return $grupos->map(function (Collection $lineas) {
            $primera = $lineas->first();
            $causaExencion = $primera->causa_exencion?->value;
            $calificacion = $primera->calificacion_operacion?->value ?? CalificacionOperacion::S1->value;

            $sinCuota = $causaExencion !== null
                || $calificacion === CalificacionOperacion::S2->value
                || $calificacion === CalificacionOperacion::N1->value
                || $calificacion === CalificacionOperacion::N2->value;

            return [
                'calificacionOperacion' => $causaExencion === null ? $calificacion : null,
                'causaExencion' => $causaExencion,
                'tipoImpositivo' => $sinCuota ? null : (float) $primera->tipo_impositivo,
                'base' => round((float) $lineas->sum('base'), 2),
                'cuotaRepercutida' => $sinCuota ? null : round((float) $lineas->sum('cuota_impuesto'), 2),
            ];
        })->values()->all();
    }

    private function codigoImpuesto(RegimenImpositivo $regimen): string
    {
        return match ($regimen) {
            RegimenImpositivo::Iva => '01',
            RegimenImpositivo::Ipsi => '02',
            RegimenImpositivo::Igic => '03',
        };
    }

    /**
     * Hora local del tenant (Principio III: la fuente es siempre el reloj del servidor en UTC;
     * esto solo la expresa en el huso horario aplicable) con offset ISO 8601, formato exigido por
     * `FechaHoraHusoGenRegistro` (docs/02 §1.1).
     */
    private function fechaHoraHusoGenRegistro(int $tenantId): string
    {
        return ConfigTenant::paraMostrar(now(), $tenantId)->format('Y-m-d\TH:i:sP');
    }

    private function importe(float $valor): string
    {
        return number_format($valor, 2, '.', '');
    }

    private function append(\DOMDocument $doc, \DOMElement $padre, string $nombre, string $valor): void
    {
        $padre->appendChild($doc->createElement($nombre, htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8')));
    }

    private function sistemaInformatico(\DOMDocument $doc, int $tenantId): \DOMElement
    {
        $config = config('verifactu.sistema_informatico');

        $el = $doc->createElement('SistemaInformatico');
        $this->append($doc, $el, 'NombreRazon', (string) $config['nombre_razon']);
        $this->append($doc, $el, 'NIF', (string) $config['nif']);
        $this->append($doc, $el, 'NombreSistemaInformatico', (string) $config['nombre_sistema_informatico']);
        $this->append($doc, $el, 'IdSistemaInformatico', (string) $config['id_sistema_informatico']);
        $this->append($doc, $el, 'Version', (string) $config['version']);
        $this->append($doc, $el, 'NumeroInstalacion', (string) $tenantId);
        $this->append($doc, $el, 'TipoUsoPosibleSoloVerifactu', 'S');
        $this->append($doc, $el, 'TipoUsoPosibleMultiOT', 'S');
        $this->append($doc, $el, 'IndicadorMultiplesOT', 'S');

        return $el;
    }
}
