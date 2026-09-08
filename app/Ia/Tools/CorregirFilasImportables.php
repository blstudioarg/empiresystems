<?php

namespace App\Ia\Tools;

use App\Exceptions\ImportacionInvalidaException;
use App\Ia\Tools\Concerns\TrabajaConMaterialImportable;
use App\Services\AlmacenImportaciones;

/**
 * Tool de lectura que **muta el borrador, no la base de datos** (feature 046, contrato §4).
 *
 * Es de lectura a efectos del asistente —se ejecuta directo, sin tarjeta de confirmación— porque su
 * efecto es sobre un borrador efímero que se descarta al terminar la conversación. Lo que sí exige
 * confirmación es importar, que es donde se escribe.
 *
 * Una corrección sobre un índice o un campo que no existen **falla explícitamente**: darla por
 * aplicada sin estarlo es el error más difícil de detectar de esta feature.
 */
class CorregirFilasImportables extends ToolAsistente
{
    use TrabajaConMaterialImportable;

    public function nombre(): string
    {
        return 'corregir_filas_importables';
    }

    public function descripcion(): string
    {
        return 'Aplica al material adjunto los datos que la persona te dictó y/o descarta registros concretos, '
            .'y devuelve el análisis actualizado. No escribe nada en la base de datos: solo corrige el borrador. '
            .'Usá únicamente valores que la persona te haya dado; no rellenes nada por tu cuenta.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'token' => ['type' => 'string', 'description' => 'Identificador del material adjunto.'],
                'correcciones' => [
                    'type' => 'array',
                    'description' => 'Datos que la persona aportó, uno por campo y registro.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'indice' => ['type' => 'integer', 'description' => 'Número de fila tal como lo devolvió el análisis.'],
                            'campo' => ['type' => 'string', 'description' => 'Clave interna del campo (la de campos_corregibles).'],
                            'valor' => ['type' => 'string'],
                        ],
                        'required' => ['indice', 'campo', 'valor'],
                    ],
                ],
                'descartar' => [
                    'type' => 'array',
                    'description' => 'Números de fila que la persona decidió dejar fuera de la importación.',
                    'items' => ['type' => 'integer'],
                ],
            ],
            'required' => ['token'],
        ];
    }

    public function permisoRequerido(): string
    {
        return 'ver-clientes';
    }

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
            foreach ((array) ($parametros['correcciones'] ?? []) as $correccion) {
                $campo = (string) ($correccion['campo'] ?? '');

                $borrador->corregir(
                    (int) ($correccion['indice'] ?? -1),
                    $campo,
                    // Se normaliza con la misma función que aplica el importador al leer una celda:
                    // un "sí" dictado en el chat tiene que llegar al validador igual que un "Sí"
                    // escrito en el fichero.
                    $this->normalizarValor($borrador, $campo, $correccion['valor'] ?? null),
                );
            }

            foreach ((array) ($parametros['descartar'] ?? []) as $indice) {
                $borrador->descartar((int) $indice);
            }
        } catch (ImportacionInvalidaException $e) {
            // No se guarda nada: si una corrección del lote no se pudo aplicar, el modelo tiene que
            // enterarse y volver a pedirla, no quedarse con medio lote aplicado sin saber cuál.
            return ['error' => $e->getMessage()];
        }

        app(AlmacenImportaciones::class)->guardarBorrador($borrador);

        // Análisis actualizado tras cada corrección (FR-014): el modelo no deduce el nuevo estado,
        // lo recibe.
        $analisis = $this->analisisDe($borrador);
        $analisis['campos_corregibles'] = $this->camposCorregibles($borrador);

        return $analisis;
    }
}
