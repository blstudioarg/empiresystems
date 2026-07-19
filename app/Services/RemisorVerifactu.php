<?php

namespace App\Services;

use App\Enums\EntornoVerifactu;
use App\Exceptions\CertificadoInvalidoException;
use App\Exceptions\VerifactuNoRegistrableException;
use App\Models\Factura;
use App\Support\CertificadoTenant;
use Illuminate\Support\Facades\Http;

/**
 * Remisión del registro Verifactu sellado al web service de la AEAT (contracts/aeat-webservice.md,
 * docs/02-facturacion-espana.md §1.3). Transporte: Guzzle (vía el cliente HTTP de Laravel) + TLS
 * mutuo con el certificado del tenant — no `ext-soap` (research R3, Principio V). Mockeable en
 * tests con `Http::fake()` (patrón ya usado por `VerificadorVies`).
 */
class RemisorVerifactu
{
    private const ENDPOINT_PRUEBAS = 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';

    private const ENDPOINT_PRODUCCION = 'https://www1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';

    private const NAMESPACE_SUM = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

    /**
     * @throws VerifactuNoRegistrableException si la factura no tiene registro sellado
     */
    public function remitir(Factura $factura): ResultadoRemisionVerifactu
    {
        if ($factura->huella === null || $factura->registro_xml === null) {
            throw new VerifactuNoRegistrableException('La factura no tiene un registro Verifactu sellado que remitir.');
        }

        return $this->enviar($factura, $factura->registro_xml);
    }

    /**
     * Remite un registro de anulación ya generado por `RegistroVerifactu::registrarAnulacion()`
     * (su XML vive en el evento `verifactu_anulacion`, no en `facturas.registro_xml`).
     */
    public function remitirAnulacion(Factura $factura, string $xmlAnulacion): ResultadoRemisionVerifactu
    {
        return $this->enviar($factura, $xmlAnulacion);
    }

    private function enviar(Factura $factura, string $xmlRegistro): ResultadoRemisionVerifactu
    {
        try {
            ['contenido' => $p12, 'password' => $password] = CertificadoTenant::paraFirmar($factura->tenant_id);
        } catch (CertificadoInvalidoException $e) {
            return ResultadoRemisionVerifactu::errorTransporte($e->getMessage());
        }

        try {
            [$certPath, $keyPath] = $this->materializarCertificado($p12, $password);
        } catch (CertificadoInvalidoException $e) {
            return ResultadoRemisionVerifactu::errorTransporte($e->getMessage());
        }

        $entorno = $factura->verifactu_entorno ?? EntornoVerifactu::Pruebas;
        $endpoint = $entorno === EntornoVerifactu::Produccion ? self::ENDPOINT_PRODUCCION : self::ENDPOINT_PRUEBAS;

        try {
            $sobre = $this->sobreSoap($factura, $xmlRegistro);

            $respuesta = Http::withOptions(['cert' => $certPath, 'ssl_key' => $keyPath])
                ->withBody($sobre, 'text/xml; charset=utf-8')
                ->withHeaders(['SOAPAction' => ''])
                ->timeout(30)
                ->post($endpoint);

            if (! $respuesta->successful()) {
                return ResultadoRemisionVerifactu::errorTransporte("La AEAT respondió con estado HTTP {$respuesta->status()}.");
            }

            return $this->parsear($respuesta->body());
        } catch (\Throwable $e) {
            return ResultadoRemisionVerifactu::errorTransporte($e->getMessage());
        } finally {
            @unlink($certPath);
            @unlink($keyPath);
        }
    }

    private function sobreSoap(Factura $factura, string $xmlRegistro): string
    {
        $tenant = $factura->tenant;

        $doc = new \DOMDocument('1.0', 'UTF-8');

        $envelope = $doc->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Envelope');
        $doc->appendChild($envelope);

        $body = $doc->createElement('soapenv:Body');
        $envelope->appendChild($body);

        $regFactu = $doc->createElementNS(self::NAMESPACE_SUM, 'sum1:RegFactuSistemaFacturacion');
        $body->appendChild($regFactu);

        $cabecera = $doc->createElement('sum1:Cabecera');
        $regFactu->appendChild($cabecera);

        $obligado = $doc->createElement('sum1:ObligadoEmision');
        $cabecera->appendChild($obligado);
        $obligado->appendChild($doc->createElement('sum1:NombreRazon', htmlspecialchars((string) ($tenant->razon_social ?: $tenant->nombre_comercial), ENT_XML1 | ENT_QUOTES, 'UTF-8')));
        $obligado->appendChild($doc->createElement('sum1:NIF', (string) $tenant->nif));

        $registroFactura = $doc->createElement('sum1:RegistroFactura');
        $regFactu->appendChild($registroFactura);

        $interno = new \DOMDocument();
        $interno->loadXML($xmlRegistro);
        $registroFactura->appendChild($doc->importNode($interno->documentElement, true));

        return $doc->saveXML();
    }

    /**
     * Mapeo de respuesta (contracts/aeat-webservice.md): `Correcto`/`AceptadoConErrores` →
     * enviada; `Incorrecto` → rechazo. Namespaces ignorados vía `local-name()` (el prefijo exacto
     * de la respuesta real de la AEAT no está fijado aquí, solo el nombre del elemento).
     */
    private function parsear(string $cuerpo): ResultadoRemisionVerifactu
    {
        $doc = new \DOMDocument();

        if ($cuerpo === '' || ! @$doc->loadXML($cuerpo)) {
            return ResultadoRemisionVerifactu::errorTransporte('La respuesta de la AEAT no es un XML válido.');
        }

        $xpath = new \DOMXPath($doc);

        $estadoEnvio = $this->valor($xpath, '//*[local-name()="EstadoEnvio"]');
        $estadoRegistro = $this->valor($xpath, '//*[local-name()="EstadoRegistro"]');
        $csv = $this->valor($xpath, '//*[local-name()="CSV"]');
        $codigoError = $this->valor($xpath, '//*[local-name()="CodigoErrorRegistro"]');
        $descripcionError = $this->valor($xpath, '//*[local-name()="DescripcionErrorRegistro"]');

        if ($estadoEnvio === 'Incorrecto' || $estadoRegistro === 'Incorrecto') {
            return ResultadoRemisionVerifactu::rechazo($codigoError, $descripcionError);
        }

        return ResultadoRemisionVerifactu::enviada($csv);
    }

    private function valor(\DOMXPath $xpath, string $expresion): ?string
    {
        $nodos = $xpath->query($expresion);

        return ($nodos !== false && $nodos->length > 0) ? trim($nodos->item(0)->textContent) : null;
    }

    /**
     * Extrae certificado y clave privada del PKCS#12 a ficheros PEM temporales, formato que exigen
     * las opciones `cert`/`ssl_key` del cliente HTTP (Guzzle) para el TLS mutuo. Se borran en el
     * `finally` de `enviar()`.
     *
     * @return array{0: string, 1: string} rutas [certificado, clave]
     *
     * @throws CertificadoInvalidoException
     */
    private function materializarCertificado(string $contenidoP12, string $password): array
    {
        if (! openssl_pkcs12_read($contenidoP12, $datos, $password)) {
            throw new CertificadoInvalidoException('El certificado guardado ya no es válido.');
        }

        $certPath = tempnam(sys_get_temp_dir(), 'verifactu_cert_');
        $keyPath = tempnam(sys_get_temp_dir(), 'verifactu_key_');

        file_put_contents($certPath, $datos['cert']);
        file_put_contents($keyPath, $datos['pkey']);

        return [$certPath, $keyPath];
    }
}
