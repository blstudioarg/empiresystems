<?php

namespace App\Exceptions;

/**
 * Fallo al interpretar un documento de compra (feature 044). Lleva el `codigo` que la UI usa para
 * decidir si sigue con el resto del lote o lo aborta (contracts/endpoints.md, "Resumen de códigos").
 *
 * El `detalle` es el mensaje técnico del proveedor: solo se expone a quien tenga
 * `ver-configuracion`, igual que hace el asistente IA (feature 030).
 */
class DocumentoCompraException extends \RuntimeException
{
    public const IA_NO_CONFIGURADA = 'ia_no_configurada';

    public const NO_ES_DOCUMENTO_COMPRA = 'no_es_documento_compra';

    public const CLAVE_INVALIDA = 'clave_invalida';

    public const LIMITE_EXCEDIDO = 'limite_excedido';

    public const SERVICIO_NO_DISPONIBLE = 'servicio_no_disponible';

    public const INTERNO = 'interno';

    public function __construct(
        public readonly string $codigo,
        string $mensaje,
        public readonly ?string $detalle = null,
        ?\Throwable $anterior = null,
    ) {
        parent::__construct($mensaje, 0, $anterior);
    }

    public function estadoHttp(): int
    {
        return match ($this->codigo) {
            self::LIMITE_EXCEDIDO => 429,
            self::SERVICIO_NO_DISPONIBLE => 502,
            self::INTERNO => 500,
            default => 422,
        };
    }

    public static function iaNoConfigurada(): self
    {
        return new self(
            self::IA_NO_CONFIGURADA,
            'El asistente IA no está configurado para esta empresa. Configúralo en Configuración › Asistente IA.',
        );
    }

    /**
     * Traduce una excepción del proveedor de IA al código correspondiente, con el mismo criterio
     * que `AsistenteIa::responder()` (401 → clave, 429 → cuota, transporte → servicio caído).
     */
    public static function desdeProveedor(\Throwable $e): self
    {
        if ($e instanceof \OpenAI\Exceptions\ErrorException) {
            $codigo = match ($e->getStatusCode()) {
                401 => self::CLAVE_INVALIDA,
                429 => self::LIMITE_EXCEDIDO,
                default => self::INTERNO,
            };

            return new self($codigo, self::mensajeDe($codigo), $e->getMessage(), $e);
        }

        if ($e instanceof \OpenAI\Exceptions\RateLimitException) {
            return new self(self::LIMITE_EXCEDIDO, self::mensajeDe(self::LIMITE_EXCEDIDO), $e->getMessage(), $e);
        }

        if ($e instanceof \OpenAI\Exceptions\TransporterException) {
            return new self(self::SERVICIO_NO_DISPONIBLE, self::mensajeDe(self::SERVICIO_NO_DISPONIBLE), $e->getMessage(), $e);
        }

        return new self(self::INTERNO, self::mensajeDe(self::INTERNO), $e->getMessage(), $e);
    }

    private static function mensajeDe(string $codigo): string
    {
        return match ($codigo) {
            self::CLAVE_INVALIDA => 'La clave de API configurada no es válida. Revisá la configuración del asistente.',
            self::LIMITE_EXCEDIDO => 'Se alcanzó el límite de uso del servicio de IA. Probá de nuevo en unos minutos.',
            self::SERVICIO_NO_DISPONIBLE => 'No se pudo conectar con el servicio de IA. Probá de nuevo en unos instantes.',
            default => 'Ocurrió un error al interpretar el documento.',
        };
    }
}
