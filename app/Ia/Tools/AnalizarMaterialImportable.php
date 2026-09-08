<?php

namespace App\Ia\Tools;

use App\Exceptions\ImportacionInvalidaException;
use App\Ia\Tools\Concerns\TrabajaConMaterialImportable;
use App\Services\AlmacenImportaciones;

/**
 * Tool de lectura: analiza el material que la persona adjuntó y le dice al modelo qué hay de verdad
 * (feature 046, contrato §3).
 *
 * No escribe nada en la base de datos: el análisis es no destructivo por definición (FR-008). Los
 * recuentos salen del análisis y **nunca** del modelo (research D6): pedirle a un modelo que cuente
 * filas de una lista larga es una forma conocida de equivocarse.
 */
class AnalizarMaterialImportable extends ToolAsistente
{
    use TrabajaConMaterialImportable;

    public function nombre(): string
    {
        return 'analizar_material_importable';
    }

    public function descripcion(): string
    {
        return 'Analiza el fichero o documento que la persona adjuntó para importar (clientes, artículos o proveedores) '
            .'y devuelve cuántos registros se leyeron, cuántos son válidos y cuáles fallan y por qué. '
            .'No escribe nada. Usala en cuanto haya material adjunto y cada vez que quieras confirmar el estado.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'token' => ['type' => 'string', 'description' => 'Identificador del material adjunto.'],
            ],
            'required' => ['token'],
        ];
    }

    public function permisoRequerido(): string
    {
        return 'ver-clientes';
    }

    /**
     * Sirve a los tres módulos importables: se ofrece a quien pueda con alguno, y el permiso del
     * módulo concreto se re-exige al ejecutar, con el módulo ya conocido (FR-021).
     */
    public function permisosAlternativos(): array
    {
        return array_values(array_diff(array_values(self::modulosImportables()), [$this->permisoRequerido()]));
    }

    public function esLectura(): bool
    {
        return true;
    }

    public function ejecutar(array $parametros): array
    {
        $borrador = $this->borradorDe((string) ($parametros['token'] ?? ''));

        if ($borrador === null) {
            return ['error' => 'No encuentro ese material. Pedile a la persona que lo vuelva a adjuntar.'];
        }

        if (! $this->puedeSobreElModulo($borrador)) {
            return ['error' => 'La persona no tiene permiso para importar '.$borrador->modulo.'.'];
        }

        try {
            $this->consumirPendientes($borrador);
        } catch (ImportacionInvalidaException $e) {
            // Lo ya leído se conserva: solo queda pendiente lo que falló.
            app(AlmacenImportaciones::class)->guardarBorrador($borrador);

            return ['error' => $e->getMessage()];
        }

        app(AlmacenImportaciones::class)->guardarBorrador($borrador);

        try {
            $analisis = $this->analisisDe($borrador);
        } catch (ImportacionInvalidaException $e) {
            return ['error' => $e->getMessage()];
        }

        // El modelo tiene que dictar las correcciones con la clave interna, no con la etiqueta que
        // aparece en el fichero: dárselas aquí evita que las invente.
        $analisis['campos_corregibles'] = $this->camposCorregibles($borrador);

        return $analisis;
    }
}
