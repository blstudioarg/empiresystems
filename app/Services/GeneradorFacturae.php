<?php

namespace App\Services;

use App\Enums\CalificacionOperacion;
use App\Enums\CausaExencion;
use App\Enums\EstadoFactura;
use App\Enums\FormaPago;
use App\Enums\RegimenImpositivo;
use App\Exceptions\FacturaeNoGenerableException;
use App\Models\Factura;
use App\Support\CertificadoTenant;
use App\Support\ValidadorIdentificacionFiscal;
use Illuminate\Support\Facades\Storage;
use josemmo\Facturae\Facturae;
use josemmo\Facturae\FacturaeItem;
use josemmo\Facturae\FacturaeParty;
use josemmo\Facturae\FacturaePayment;

/**
 * Mapea una factura emitida (Factura+FacturaLinea+FacturaImpuesto) al modelo de
 * `josemmo/facturae-php`, la firma con el certificado del tenant (XAdES-EPES) y devuelve el XML
 * Facturae 3.2.2 firmado. Todo server-side (Principio III): ningún importe se recalcula, se leen
 * tal cual de la factura ya emitida e inmutable.
 */
class GeneradorFacturae
{
    /**
     * @throws FacturaeNoGenerableException
     * @throws \App\Exceptions\CertificadoInvalidoException
     */
    public function generar(Factura $factura): string
    {
        if ($factura->estado !== EstadoFactura::Emitida) {
            throw new FacturaeNoGenerableException('Solo se pueden generar Facturae de facturas emitidas.');
        }

        $factura->loadMissing(['lineas', 'impuestos', 'tenant', 'serie']);

        if (! ValidadorIdentificacionFiscal::esValido($factura->tenant->nif)) {
            throw new FacturaeNoGenerableException('El NIF del emisor no es válido: revisa los datos fiscales del tenant.');
        }

        if (! ValidadorIdentificacionFiscal::esValido($factura->cliente_nif)) {
            throw new FacturaeNoGenerableException('El NIF del cliente no es válido: revisa los datos fiscales del cliente.');
        }

        ['contenido' => $certificado, 'password' => $password] = CertificadoTenant::paraFirmar($factura->tenant_id);

        $fac = new Facturae(Facturae::SCHEMA_3_2_2);
        $fac->setNumber($factura->serie?->codigo ?? '', (string) ($factura->numero ?? $factura->numero_completo));
        $fac->setIssueDate($factura->fecha_expedicion->toDateString());

        $fac->setSeller($this->partyDesdeTenant($factura));
        $fac->setBuyer($this->partyDesdeCliente($factura));

        $this->aplicarFormaPago($fac, $factura);

        $legalLiterals = [];

        foreach ($factura->lineas as $linea) {
            [$item, $literal] = $this->itemDesdeLinea($linea, $factura);
            $fac->addItem($item);

            if ($literal !== null && ! in_array($literal, $legalLiterals, true)) {
                $legalLiterals[] = $literal;
            }
        }

        foreach ($legalLiterals as $literal) {
            $fac->addLegalLiteral($literal);
        }

        $fac->sign($certificado, null, $password);

        return $fac->export();
    }

    /**
     * Genera (si no existe) y conserva el XML Facturae asociado a la factura en disco privado.
     * Si ya se generó antes, devuelve el archivo conservado sin regenerar (FR-006).
     *
     * @return array{xml: string, ruta: string, regenerado: bool}
     *
     * @throws FacturaeNoGenerableException
     * @throws \App\Exceptions\CertificadoInvalidoException
     */
    public function generarYConservar(Factura $factura): array
    {
        $ruta = $this->rutaArchivo($factura);

        if (Storage::disk('documentos')->exists($ruta)) {
            return ['xml' => Storage::disk('documentos')->get($ruta), 'ruta' => $ruta, 'regenerado' => false];
        }

        $xml = $this->generar($factura);
        Storage::disk('documentos')->put($ruta, $xml);

        return ['xml' => $xml, 'ruta' => $ruta, 'regenerado' => true];
    }

    /**
     * Lee el XML ya conservado sin generar nada nuevo; `null` si aún no se generó.
     */
    public function leerConservado(Factura $factura): ?string
    {
        $ruta = $this->rutaArchivo($factura);

        return Storage::disk('documentos')->exists($ruta) ? Storage::disk('documentos')->get($ruta) : null;
    }

    public function rutaArchivo(Factura $factura): string
    {
        return "tenants/{$factura->tenant_id}/facturae/factura-{$factura->id}.xsig";
    }

    private function partyDesdeTenant(Factura $factura): FacturaeParty
    {
        $tenant = $factura->tenant;

        return new FacturaeParty([
            'taxNumber' => $tenant->nif,
            'name' => $tenant->razon_social ?: $tenant->nombre_comercial,
            'address' => $tenant->direccion,
            'postCode' => $tenant->cp,
            'town' => $tenant->ciudad,
            'province' => $tenant->provincia,
            'countryCode' => $this->countryCode($tenant->pais),
        ]);
    }

    private function partyDesdeCliente(Factura $factura): FacturaeParty
    {
        return new FacturaeParty([
            'taxNumber' => $factura->cliente_nif,
            'name' => $factura->cliente_razon_social ?: $factura->cliente_nombre,
            'address' => $factura->cliente_direccion,
            'postCode' => $factura->cliente_cp,
            'town' => $factura->cliente_ciudad,
            'province' => $factura->cliente_provincia,
            'countryCode' => $this->countryCode($factura->cliente_pais),
        ]);
    }

    private function countryCode(?string $pais): string
    {
        // FacturaeParty espera ISO 3166-1 alfa-3; el dominio guarda alfa-2 (ES). Mapeo mínimo
        // para el caso mayoritario español; el resto cae en el valor recibido.
        return match (strtoupper((string) $pais)) {
            'ES' => 'ESP',
            '' => 'ESP',
            default => strlen((string) $pais) === 2 ? strtoupper($pais) : (string) $pais,
        };
    }

    private function aplicarFormaPago(Facturae $fac, Factura $factura): void
    {
        $metodo = match ($factura->forma_pago) {
            FormaPago::Transferencia => FacturaePayment::TYPE_TRANSFER,
            FormaPago::Tarjeta => FacturaePayment::TYPE_CARD,
            FormaPago::Efectivo => FacturaePayment::TYPE_CASH,
            FormaPago::Domiciliacion => FacturaePayment::TYPE_DEBIT,
            default => FacturaePayment::TYPE_CASH,
        };

        $fac->addPayment(new FacturaePayment([
            'method' => $metodo,
            'iban' => $factura->cuenta_bancaria_iban,
        ]));
    }

    private function tipoImpuestoFacturae(RegimenImpositivo $regimen): string
    {
        return match ($regimen) {
            RegimenImpositivo::Iva => Facturae::TAX_IVA,
            RegimenImpositivo::Igic => Facturae::TAX_IGIC,
            RegimenImpositivo::Ipsi => Facturae::TAX_IPSI,
        };
    }

    /**
     * @return array{0: FacturaeItem, 1: string|null} El item Facturae y, si aplica (ISP), la
     *     mención legal a añadir a nivel de factura (el esquema Facturae no tiene un campo propio
     *     para inversión del sujeto pasivo; se refleja como mención legal, docs/02 §6).
     */
    private function itemDesdeLinea($linea, Factura $factura): array
    {
        $taxType = $this->tipoImpuestoFacturae($factura->regimen_impositivo);
        $base = (float) $linea->base;

        $taxes = [];
        $literalFactura = null;
        $specialCode = null;
        $specialReason = null;

        $calificacion = $linea->calificacion_operacion;
        $causaExencion = $linea->causa_exencion;

        if ($causaExencion instanceof CausaExencion) {
            $specialCode = FacturaeItem::SPECIAL_TAXABLE_EVENT_EXEMPT;
            $specialReason = $linea->mencion_legal ?? $causaExencion->mencionLegalSugerida();
        } elseif ($calificacion === CalificacionOperacion::N1 || $calificacion === CalificacionOperacion::N2) {
            $specialCode = FacturaeItem::SPECIAL_TAXABLE_EVENT_NON_SUBJECT;
            $specialReason = $linea->mencion_legal ?? $calificacion->mencionLegalSugerida();
        } elseif ($calificacion === CalificacionOperacion::S2) {
            // ISP: sujeta, sin cuota repercutida. Sin campo propio en el esquema; se añade como
            // mención legal a nivel de factura.
            $literalFactura = $linea->mencion_legal ?? $calificacion->mencionLegalSugerida();
            $taxes[$taxType] = ['rate' => 0, 'surcharge' => 0, 'isWithheld' => false];
        } else {
            $taxes[$taxType] = [
                'rate' => (float) $linea->tipo_impositivo,
                'surcharge' => (float) ($linea->tipo_recargo ?? 0),
                'isWithheld' => false,
            ];
        }

        if ($factura->irpf_porcentaje !== null && (float) $factura->irpf_porcentaje > 0) {
            $taxes[Facturae::TAX_IRPF] = [
                'rate' => (float) $factura->irpf_porcentaje,
                'surcharge' => 0,
                'isWithheld' => true,
            ];
        }

        $item = new FacturaeItem([
            'name' => $linea->concepto,
            'quantity' => 1,
            'unitPriceWithoutTax' => $base,
            'taxes' => $taxes,
            'specialTaxableEventCode' => $specialCode,
            'specialTaxableEventReason' => $specialReason,
        ]);

        return [$item, $literalFactura];
    }
}
