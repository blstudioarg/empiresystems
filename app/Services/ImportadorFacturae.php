<?php

namespace App\Services;

use App\Exceptions\FacturaeImportacionException;
use App\Models\Compra;
use App\Models\Proveedor;
use App\Support\VerificadorFirmaFacturae;
use DOMDocument;
use DOMElement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Parsea un XML Facturae recibido de un proveedor y lo vuelca en una `Compra` (`origen=facturae`).
 * `josemmo/facturae-php` no incluye parser de lectura; se interpreta el XML directamente con DOM
 * (mismo esquema/estructura que genera la librería al exportar, ver `GeneradorFacturae`).
 */
class ImportadorFacturae
{
    /**
     * @return array{compra: Compra|null, duplicado: bool, firma_verificable: bool}
     *
     * @throws FacturaeImportacionException
     */
    public function importar(UploadedFile $archivo, int $tenantId): array
    {
        $contenido = file_get_contents($archivo->getRealPath());
        $datos = $this->parsear($contenido);

        $proveedor = $this->resolverProveedor($datos['emisor'], $tenantId);

        $duplicado = Compra::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('numero_documento', $datos['numeroDocumento'])
            ->whereDate('fecha', $datos['fecha'])
            ->exists();

        if ($duplicado) {
            return ['compra' => null, 'duplicado' => true, 'firma_verificable' => false];
        }

        $firmaVerificable = VerificadorFirmaFacturae::tieneFirma($contenido)
            ? VerificadorFirmaFacturae::esVerificable($contenido)
            : true; // Sin firma: no hay nada que verificar, no se marca como aviso de firma inválida.

        $ruta = 'tenants/'.$tenantId.'/facturae-recibidas/'.Str::uuid()->toString().'.xml';
        Storage::disk('documentos')->put($ruta, $contenido);

        $compra = DB::transaction(function () use ($datos, $proveedor, $ruta) {
            $compra = Compra::create([
                'proveedor_id' => $proveedor->id,
                'numero_documento' => $datos['numeroDocumento'],
                'fecha' => $datos['fecha'],
                'base_total' => $datos['baseTotal'],
                'cuota_impuesto_total' => $datos['cuotaImpuestoTotal'],
                'total' => $datos['total'],
                'origen' => 'facturae',
                'formato_recepcion' => 'facturae',
                'archivo_recibido_path' => $ruta,
                'estado_b2b' => 'recibida',
                'estado_b2b_fecha' => now(),
            ]);

            foreach ($datos['lineas'] as $orden => $linea) {
                $compra->lineas()->create([
                    'concepto' => $linea['concepto'],
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $linea['precioUnitario'],
                    'base' => $linea['base'],
                    'tipo_impositivo' => $linea['tipoImpositivo'],
                    'cuota_impuesto' => $linea['cuotaImpuesto'],
                    'orden' => $orden,
                ]);
            }

            return $compra;
        });

        return ['compra' => $compra, 'duplicado' => false, 'firma_verificable' => $firmaVerificable];
    }

    /**
     * @return array{
     *     emisor: array{nif: string, nombre: string, direccion: ?string, cp: ?string, ciudad: ?string, provincia: ?string, pais: string},
     *     numeroDocumento: string,
     *     fecha: string,
     *     baseTotal: float,
     *     cuotaImpuestoTotal: float,
     *     total: float,
     *     lineas: array<int, array{concepto: string, cantidad: float, precioUnitario: float, base: float, tipoImpositivo: float, cuotaImpuesto: float}>,
     * }
     *
     * @throws FacturaeImportacionException
     */
    private function parsear(string $contenido): array
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument;
        $cargado = $doc->loadXML($contenido);
        libxml_clear_errors();

        if (! $cargado || $doc->documentElement === null || $doc->documentElement->localName !== 'Facturae') {
            throw new FacturaeImportacionException('El archivo no es un XML Facturae válido (esquema roto).');
        }

        $sellerParty = $doc->getElementsByTagName('SellerParty')->item(0);

        if (! $sellerParty instanceof DOMElement) {
            throw new FacturaeImportacionException('El Facturae no incluye los datos del emisor.');
        }

        $emisor = $this->extraerParty($sellerParty);

        $numero = $this->primerTexto($doc, 'InvoiceNumber');
        $serie = $this->primerTexto($doc, 'InvoiceSeriesCode');
        $fecha = $this->primerTexto($doc, 'IssueDate');

        if ($numero === null || $fecha === null) {
            throw new FacturaeImportacionException('El Facturae no incluye número o fecha de emisión.');
        }

        $numeroDocumento = $serie !== null && $serie !== '' ? "{$serie}-{$numero}" : $numero;

        $baseTotal = (float) ($this->primerTexto($doc, 'TotalGrossAmountBeforeTaxes') ?? 0);
        $cuotaImpuestoTotal = (float) ($this->primerTexto($doc, 'TotalTaxOutputs') ?? 0);
        $total = (float) ($this->primerTexto($doc, 'InvoiceTotal') ?? 0);

        if (abs(($baseTotal + $cuotaImpuestoTotal) - $total) > 0.02) {
            throw new FacturaeImportacionException('Los importes del Facturae son incoherentes (base + cuota ≠ total).');
        }

        $lineas = [];

        foreach ($doc->getElementsByTagName('InvoiceLine') as $lineaNode) {
            if (! $lineaNode instanceof DOMElement) {
                continue;
            }

            $concepto = $this->primerTextoEn($lineaNode, 'ItemDescription') ?? 'Sin descripción';
            $cantidad = (float) ($this->primerTextoEn($lineaNode, 'Quantity') ?? 1);
            $precioUnitario = (float) ($this->primerTextoEn($lineaNode, 'UnitPriceWithoutTax') ?? 0);
            $base = (float) ($this->primerTextoEn($lineaNode, 'TotalCost') ?? 0);

            $tipoImpositivo = 0.0;
            $cuotaImpuesto = 0.0;
            $taxesOutputs = $lineaNode->getElementsByTagName('TaxesOutputs')->item(0);

            if ($taxesOutputs instanceof DOMElement) {
                $taxNode = $taxesOutputs->getElementsByTagName('Tax')->item(0);

                if ($taxNode instanceof DOMElement) {
                    $tipoImpositivo = (float) ($this->primerTextoEn($taxNode, 'TaxRate') ?? 0);
                    $cuotaImpuesto = (float) ($this->primerTextoEn($taxNode, 'TaxAmount') ?? 0);
                }
            }

            $lineas[] = [
                'concepto' => $concepto,
                'cantidad' => $cantidad,
                'precioUnitario' => $precioUnitario,
                'base' => $base,
                'tipoImpositivo' => $tipoImpositivo,
                'cuotaImpuesto' => $cuotaImpuesto,
            ];
        }

        if (count($lineas) === 0) {
            throw new FacturaeImportacionException('El Facturae no contiene ninguna línea de factura.');
        }

        return [
            'emisor' => $emisor,
            'numeroDocumento' => $numeroDocumento,
            'fecha' => $fecha,
            'baseTotal' => round($baseTotal, 2),
            'cuotaImpuestoTotal' => round($cuotaImpuestoTotal, 2),
            'total' => round($total, 2),
            'lineas' => $lineas,
        ];
    }

    /**
     * @return array{nif: string, nombre: string, direccion: ?string, cp: ?string, ciudad: ?string, provincia: ?string, pais: string}
     *
     * @throws FacturaeImportacionException
     */
    private function extraerParty(DOMElement $party): array
    {
        $nif = $this->primerTextoEn($party, 'TaxIdentificationNumber');

        if ($nif === null || $nif === '') {
            throw new FacturaeImportacionException('El Facturae no incluye el NIF del emisor.');
        }

        $nombre = $this->primerTextoEn($party, 'CorporateName') ?? $this->primerTextoEn($party, 'Name') ?? $nif;
        $direccion = $this->primerTextoEn($party, 'Address');
        $cp = $this->primerTextoEn($party, 'PostCode');
        $ciudad = $this->primerTextoEn($party, 'Town');
        $provincia = $this->primerTextoEn($party, 'Province');
        $countryCode = $this->primerTextoEn($party, 'CountryCode') ?? 'ESP';

        return [
            'nif' => $nif,
            'nombre' => $nombre,
            'direccion' => $direccion,
            'cp' => $cp,
            'ciudad' => $ciudad,
            'provincia' => $provincia,
            'pais' => $countryCode === 'ESP' ? 'ES' : substr($countryCode, 0, 2),
        ];
    }

    /**
     * @param  array{nif: string, nombre: string, direccion: ?string, cp: ?string, ciudad: ?string, provincia: ?string, pais: string}  $emisor
     */
    private function resolverProveedor(array $emisor, int $tenantId): Proveedor
    {
        $proveedor = Proveedor::query()->where('nif', $emisor['nif'])->first();

        if ($proveedor !== null) {
            return $proveedor;
        }

        return Proveedor::create([
            'tenant_id' => $tenantId,
            'nombre' => $emisor['nombre'],
            'razon_social' => $emisor['nombre'],
            'nif' => $emisor['nif'],
            'direccion' => $emisor['direccion'],
            'cp' => $emisor['cp'],
            'ciudad' => $emisor['ciudad'],
            'provincia' => $emisor['provincia'],
            'pais' => $emisor['pais'],
        ]);
    }

    private function primerTexto(DOMDocument $doc, string $tag): ?string
    {
        $node = $doc->getElementsByTagName($tag)->item(0);

        return $node !== null ? trim($node->textContent) : null;
    }

    private function primerTextoEn(DOMElement $contexto, string $tag): ?string
    {
        $node = $contexto->getElementsByTagName($tag)->item(0);

        return $node !== null ? trim($node->textContent) : null;
    }
}
