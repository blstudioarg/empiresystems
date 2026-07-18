<?php

namespace App\Ia\Tools;

/**
 * Contrato base de una tool del asistente IA (feature 030, data-model §3).
 *
 * Cada tool declara su nombre, descripción, schema JSON y el permiso de sección que exige.
 * Las tools de lectura (`esLectura() === true`) se ejecutan directo; las de escritura generan
 * una acción pendiente vía `proponer()` y solo se ejecutan desde el endpoint de confirmación.
 *
 * Invariante de seguridad #1: ninguna tool acepta `tenant_id` en su schema; todas operan bajo
 * el TenantScope del request.
 */
abstract class ToolAsistente
{
    /** Identificador de la tool para la API (snake_case, p. ej. `buscar_clientes`). */
    abstract public function nombre(): string;

    /** Descripción para el modelo: qué hace y cuándo usarla. */
    abstract public function descripcion(): string;

    /**
     * JSON Schema de los parámetros de entrada.
     *
     * @return array<string, mixed>
     */
    abstract public function schema(): array;

    /** Clave del CatalogoPermisos que exige la tool (p. ej. `ver-clientes`). */
    abstract public function permisoRequerido(): string;

    /** true = lectura (ejecuta directo); false = escritura (requiere confirmación). */
    abstract public function esLectura(): bool;

    /**
     * Ejecuta la acción.
     * - Lectura: se llama directo en el loop y devuelve el resultado.
     * - Escritura: solo se invoca desde el endpoint de confirmación, con los parámetros
     *   ya normalizados por `proponer()`.
     *
     * @param  array<string, mixed>  $parametros
     * @return array<string, mixed>
     */
    abstract public function ejecutar(array $parametros): array;

    /**
     * Solo escrituras: valida los parámetros y devuelve un resumen legible + los parámetros
     * normalizados que se persistirán como acción pendiente. Por defecto lanza excepción en
     * lecturas (no aplica).
     *
     * @param  array<string, mixed>  $parametros
     * @return array{resumen: string, parametros: array<string, mixed>, url?: string|null}
     */
    public function proponer(array $parametros): array
    {
        throw new \LogicException("La tool {$this->nombre()} es de lectura y no propone acciones.");
    }

    /**
     * Definición de la tool en el formato de function calling de OpenAI (Chat Completions).
     *
     * @return array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}
     */
    public function definicion(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->nombre(),
                'description' => $this->descripcion(),
                'parameters' => $this->schema(),
            ],
        ];
    }
}
