<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;

/**
 * Verifica si la firma XAdES/XMLDSig de un XML Facturae es criptográficamente válida contra el
 * certificado embebido en la propia firma (`ds:X509Certificate`). No valida la cadena de confianza
 * (CA, revocación): solo que la firma no ha sido alterada, suficiente para el aviso de FR-016a.
 */
class VerificadorFirmaFacturae
{
    public static function tieneFirma(string $xml): bool
    {
        $doc = new DOMDocument;

        if (@$doc->loadXML($xml) === false) {
            return false;
        }

        $xp = new DOMXPath($doc);
        $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        return $xp->query('//ds:Signature')->length > 0;
    }

    public static function esVerificable(string $xml): bool
    {
        $doc = new DOMDocument;

        if (@$doc->loadXML($xml) === false) {
            return false;
        }

        $xp = new DOMXPath($doc);
        $xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $signedInfo = $xp->query('//ds:Signature/ds:SignedInfo')->item(0);
        $sigValueNode = $xp->query('//ds:Signature/ds:SignatureValue')->item(0);
        $certNode = $xp->query('//ds:Signature//ds:X509Certificate')->item(0);

        if (! $signedInfo || ! $sigValueNode || ! $certNode) {
            return false;
        }

        $sigValue = base64_decode(preg_replace('/\s+/', '', $sigValueNode->textContent));
        $certB64 = preg_replace('/\s+/', '', $certNode->textContent);
        $pem = "-----BEGIN CERTIFICATE-----\n".chunk_split($certB64, 64, "\n")."-----END CERTIFICATE-----\n";
        $publicKey = openssl_pkey_get_public($pem);

        if (! $publicKey) {
            return false;
        }

        $canonicalizationNode = $xp->query('//ds:Signature/ds:SignedInfo/ds:CanonicalizationMethod')->item(0);
        $exclusive = $canonicalizationNode && str_contains($canonicalizationNode->getAttribute('Algorithm'), 'xml-exc-c14n');

        $signatureMethodNode = $xp->query('//ds:Signature/ds:SignedInfo/ds:SignatureMethod')->item(0);
        $algoUri = $signatureMethodNode ? $signatureMethodNode->getAttribute('Algorithm') : '';
        $algoritmo = match (true) {
            str_contains($algoUri, 'sha512') => OPENSSL_ALGO_SHA512,
            str_contains($algoUri, 'sha256') => OPENSSL_ALGO_SHA256,
            default => OPENSSL_ALGO_SHA1,
        };

        foreach ([$exclusive, ! $exclusive] as $modo) {
            $canonico = $signedInfo->C14N($modo, false);
            if (openssl_verify($canonico, $sigValue, $publicKey, $algoritmo) === 1) {
                return true;
            }
        }

        return false;
    }
}
