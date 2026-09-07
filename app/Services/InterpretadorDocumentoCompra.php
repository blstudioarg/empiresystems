<?php

namespace App\Services;

use App\Exceptions\DocumentoCompraException;
use App\Support\IaTenant;

/**
 * Única pieza que habla con el proveedor de IA en esta feature (feature 044).
 *
 * El documento viaja en base64 tal cual: no se rasteriza en local porque el hosting compartido no
 * tiene Imagick ni Ghostscript y añadirlos violaría el Principio V (research D1).
 *
 * Devuelve la lectura cruda conformada por `contracts/propuesta-compra.schema.json`. **No conoce
 * Eloquent ni el catálogo del tenant**: traducir eso a negocio es trabajo de
 * `ProponedorCompraDesdeDocumento`, y esa separación es la que permite probar el cálculo de
 * importes sin red ni clave de API (research D3).
 */
class InterpretadorDocumentoCompra
{
    private const SYSTEM_PROMPT = <<<'TXT'
    Sos un extractor de datos de documentos de compra españoles (facturas y albaranes de proveedor).

    Devolvés únicamente los datos que LEÉS en el documento. Reglas que no podés romper:

    - No inventes ningún valor. Si un dato no se lee con seguridad, devolvé null.
    - El emisor es el PROVEEDOR (quien cobra), nunca el receptor (quien paga). En una factura
      española el receptor suele ir bajo "Cliente" o "Facturar a": ese no es el emisor.
    - El tipo impositivo es un porcentaje: 21, 10, 4, 7, 0… Puede ser IVA, IGIC (Canarias) o IPSI
      (Ceuta/Melilla). Si el documento no lo indica, devolvé null. NUNCA asumas 21.
    - Los precios unitarios van SIN impuestos. Si el documento solo da el importe total de la
      línea, dividilo por la cantidad.
    - Las fechas en formato YYYY-MM-DD. Un documento español escribe DD/MM/AAAA: 03/04/2026 es el
      3 de abril, no el 4 de marzo. Si no hay fecha legible, null: no uses la de hoy.
    - En las líneas incluí solo el detalle real. Excluí subtotales, totales, bases imponibles,
      descuentos globales y textos legales.
    TXT;

    /**
     * @return array<string, mixed> Lectura cruda conforme al schema.
     *
     * @throws DocumentoCompraException
     */
    public function interpretar(string $rutaAbsoluta, string $archivoNombre, string $extension): array
    {
        $apiKey = IaTenant::apiKey();

        if ($apiKey === '') {
            throw DocumentoCompraException::iaNoConfigurada();
        }

        $contenido = @file_get_contents($rutaAbsoluta);

        if ($contenido === false) {
            throw new DocumentoCompraException(
                DocumentoCompraException::INTERNO,
                'No se pudo leer el documento subido.',
            );
        }

        try {
            $respuesta = \OpenAI::client($apiKey)->chat()->create([
                'model' => config('ia.modelo'),
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => 'Extraé los datos de este documento de compra.'],
                            $this->parteDelDocumento($contenido, $archivoNombre, $extension),
                        ],
                    ],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'lectura_documento_compra',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            throw DocumentoCompraException::desdeProveedor($e);
        }

        $json = $respuesta->choices[0]->message->content ?? '';
        $lectura = json_decode($json, true);

        if (! is_array($lectura)) {
            throw new DocumentoCompraException(
                DocumentoCompraException::INTERNO,
                'La respuesta del servicio de IA no se pudo interpretar.',
                'Respuesta no decodificable como JSON.',
            );
        }

        if (($lectura['es_documento_compra'] ?? false) !== true) {
            throw new DocumentoCompraException(
                DocumentoCompraException::NO_ES_DOCUMENTO_COMPRA,
                "No se pudo interpretar «{$archivoNombre}» como un documento de compra.",
                $lectura['motivo_descarte'] ?? null,
            );
        }

        return $lectura;
    }

    /**
     * Una imagen viaja como `image_url` con data URI; un PDF como parte `file` con `file_data`
     * (research D1): son los dos formatos que el proveedor acepta sin conversión previa.
     *
     * @return array<string, mixed>
     */
    private function parteDelDocumento(string $contenido, string $archivoNombre, string $extension): array
    {
        $base64 = base64_encode($contenido);

        if ($extension === 'pdf') {
            return [
                'type' => 'file',
                'file' => [
                    'filename' => $archivoNombre,
                    'file_data' => 'data:application/pdf;base64,'.$base64,
                ],
            ];
        }

        $mime = match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return [
            'type' => 'image_url',
            'image_url' => ['url' => "data:{$mime};base64,".$base64],
        ];
    }

    /**
     * Espejo de `contracts/propuesta-compra.schema.json`. Con `strict: true` el proveedor exige
     * `additionalProperties: false` y que **todas** las propiedades estén en `required`: lo
     * opcional se expresa admitiendo `null` en el tipo, no omitiendo el campo.
     *
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        $textoNullable = ['type' => ['string', 'null']];
        $numeroNullable = ['type' => ['number', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['es_documento_compra', 'motivo_descarte', 'emisor', 'numero_documento', 'fecha', 'moneda', 'lineas', 'total_documento'],
            'properties' => [
                'es_documento_compra' => ['type' => 'boolean'],
                'motivo_descarte' => $textoNullable,
                'emisor' => [
                    'type' => ['object', 'null'],
                    'additionalProperties' => false,
                    'required' => ['nombre', 'razon_social', 'nif', 'direccion', 'cp', 'ciudad', 'provincia', 'pais', 'email', 'telefono'],
                    'properties' => [
                        'nombre' => $textoNullable,
                        'razon_social' => $textoNullable,
                        'nif' => $textoNullable,
                        'direccion' => $textoNullable,
                        'cp' => $textoNullable,
                        'ciudad' => $textoNullable,
                        'provincia' => $textoNullable,
                        'pais' => $textoNullable,
                        'email' => $textoNullable,
                        'telefono' => $textoNullable,
                    ],
                ],
                'numero_documento' => $textoNullable,
                'fecha' => $textoNullable,
                'moneda' => $textoNullable,
                'lineas' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['referencia', 'concepto', 'unidad', 'cantidad', 'precio_unitario', 'tipo_impositivo'],
                        'properties' => [
                            'referencia' => $textoNullable,
                            'concepto' => ['type' => 'string'],
                            'unidad' => $textoNullable,
                            'cantidad' => $numeroNullable,
                            'precio_unitario' => $numeroNullable,
                            'tipo_impositivo' => $numeroNullable,
                        ],
                    ],
                ],
                'total_documento' => $numeroNullable,
            ],
        ];
    }
}
