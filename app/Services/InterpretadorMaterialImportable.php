<?php

namespace App\Services;

use App\Excel\ColumnaExcel;
use App\Excel\DefinicionImportable;
use App\Exceptions\ImportacionInvalidaException;
use App\Support\ContadorPaginasPdf;
use App\Support\IaTenant;
use App\Support\MaterialImportable;

/**
 * Única pieza que habla con el proveedor de IA en esta feature (046, research D3).
 *
 * Recibe un documento —PDF, imagen o texto— y devuelve **filas conformes a las columnas del
 * módulo**, con las claves internas de {@see ColumnaExcel}: exactamente lo que espera
 * `ImportadorExcel::analizarFilas()`. No conoce Eloquent, no valida negocio y no decide si una fila
 * vale: eso sigue siendo del importador, que delega en el validador del alta manual.
 *
 * El documento viaja en base64 tal cual, sin rasterizar: el hosting compartido no tiene Imagick ni
 * Ghostscript y añadirlos violaría el Principio V (patrón heredado de la 044).
 *
 * **La regla crítica**: no inventar. Un NIF inventado entra en el maestro de clientes y no lo
 * detecta nadie (FR-009). Por eso el prompt lo prohíbe explícitamente, el schema admite `null` en
 * todo lo que no sea imprescindible, y `leido` deja constancia de qué dijo el documento y qué no.
 */
class InterpretadorMaterialImportable
{
    private const SYSTEM_PROMPT = <<<'TXT'
    Sos un extractor de listados de registros de documentos españoles (PDF, fotos, capturas o texto
    suelto). Devolvés únicamente los datos que LEÉS en el documento.

    Reglas que no podés romper:

    - **No inventes ningún valor.** Si un dato no se lee con seguridad, devolvé null. Es preferible
      un hueco, que la persona puede rellenar, a un dato plausible pero falso, que nadie detecta.
    - Un NIF, un email o un teléfono se copian carácter a carácter. Si están cortados, borrosos o
      dudosos, null.
    - No completes con valores "razonables", ni con lo que suele ir en ese campo, ni deduzcas un
      dato de otro (no inventes el NIF a partir del nombre, ni el email a partir de la web).
    - Un registro por cada entrada real del listado. Excluí cabeceras, totales, pies de página,
      textos legales y cualquier fila que no sea un registro.
    - Si el documento no contiene ningún registro de este tipo, devolvé la lista vacía y explicá
      por qué en "avisos".
    - En "avisos" contá lo que NO pudiste leer (páginas borrosas, columnas cortadas, secciones
      ilegibles). Es información que la persona necesita para saber si falta algo.
    TXT;

    public function __construct(private readonly ContadorPaginasPdf $contadorPaginas = new ContadorPaginasPdf) {}

    /**
     * @return array{filas: array<int, array{datos: array<string, mixed>, leido: array<string, bool>}>, avisos: array<int, string>}
     *
     * @throws ImportacionInvalidaException
     */
    public function interpretar(DefinicionImportable $definicion, string $rutaAbsoluta, string $nombre, string $extension): array
    {
        $this->comprobarTope($rutaAbsoluta, $nombre, $extension);

        $contenido = @file_get_contents($rutaAbsoluta);

        if ($contenido === false || $contenido === '') {
            throw new ImportacionInvalidaException("No se ha podido leer «{$nombre}».");
        }

        $columnas = $definicion->columnas();

        $lectura = $this->pedirLectura(
            $contenido,
            $nombre,
            $extension,
            $this->schema($columnas),
            'Extraé los '.$definicion->etiquetaModulo().' de este documento.',
        );

        return $this->aFilas($columnas, $lectura);
    }

    /**
     * Traduce la lectura cruda a filas del módulo (T034).
     *
     * `leido` es la diferencia entre «el documento no lo dice» y «el documento dice que está
     * vacío», y es lo único que permite cumplir FR-009 sin inventar nada. El valor sí pasa por el
     * `importar()` de la columna —el mismo que aplica el importador al leer una celda—, para que
     * una fila interpretada entre en el pipeline exactamente igual que una de un Excel.
     *
     * @param  ColumnaExcel[]  $columnas
     * @param  array<string, mixed>  $lectura
     * @return array{filas: array<int, array{datos: array<string, mixed>, leido: array<string, bool>}>, avisos: array<int, string>}
     */
    private function aFilas(array $columnas, array $lectura): array
    {
        $filas = [];

        foreach ((array) ($lectura['registros'] ?? []) as $registro) {
            if (! is_array($registro)) {
                continue;
            }

            $datos = [];
            $leido = [];

            foreach ($columnas as $columna) {
                $crudo = $registro[$columna->clave] ?? null;

                if (is_string($crudo) && trim($crudo) === '') {
                    $crudo = null;
                }

                $leido[$columna->clave] = $crudo !== null;
                $datos[$columna->clave] = $columna->importar ? ($columna->importar)($crudo) : $crudo;
            }

            $filas[] = ['datos' => $datos, 'leido' => $leido];
        }

        $avisos = array_values(array_filter(array_map(
            static fn ($aviso) => trim((string) $aviso),
            (array) ($lectura['avisos'] ?? []),
        )));

        return ['filas' => $filas, 'avisos' => $avisos];
    }

    /**
     * Tope de páginas por importación (research D9). Se explica en la conversación con el número
     * concreto: «no se pudo» sin decir por qué no le sirve a nadie.
     *
     * @throws ImportacionInvalidaException
     */
    private function comprobarTope(string $rutaAbsoluta, string $nombre, string $extension): void
    {
        if ($extension !== 'pdf') {
            return;
        }

        $paginas = $this->contadorPaginas->contar($rutaAbsoluta);
        $tope = MaterialImportable::maxPaginas();

        // Un PDF cuyo recuento no está en claro no se bloquea: más vale aceptar uno largo que
        // rechazar uno válido (mismo criterio que la 044).
        if ($paginas !== null && $paginas > $tope) {
            throw new ImportacionInvalidaException(
                "«{$nombre}» tiene {$paginas} páginas y el máximo por importación es {$tope}. "
                .'Interpretar un documento largo cuesta y tarda mucho más que leer una hoja de cálculo: '
                .'dividilo en partes o pasame los datos en un Excel.'
            );
        }
    }

    /**
     * Schema derivado de las columnas del módulo: exportación, plantilla, importación e
     * interpretación leen la misma lista y no pueden desincronizarse.
     *
     * Con `strict: true` el proveedor exige `additionalProperties: false` y que **todas** las
     * propiedades estén en `required`: lo opcional se expresa admitiendo `null`, no omitiendo el
     * campo. Que todo admita `null` es justamente lo que hace posible no inventar (FR-009).
     *
     * @param  ColumnaExcel[]  $columnas
     * @return array<string, mixed>
     */
    private function schema(array $columnas): array
    {
        $propiedades = [];
        $requeridas = [];

        foreach ($columnas as $columna) {
            if ($columna->importar === null) {
                continue; // columna solo exportable: no se puede importar, no se pide
            }

            $propiedades[$columna->clave] = [
                'type' => ['string', 'null'],
                'description' => $columna->etiqueta.'. null si el documento no lo dice con seguridad.',
            ];
            $requeridas[] = $columna->clave;
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['registros', 'avisos'],
            'properties' => [
                'registros' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => $requeridas,
                        'properties' => $propiedades,
                    ],
                ],
                'avisos' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Lo que no se pudo leer del documento.',
                ],
            ],
        ];
    }

    /**
     * Único punto que toca la red. Aislado a propósito: sobrescribiéndolo, un test ejercita toda la
     * traducción a filas sin clave de API ni conexión (research D10).
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     *
     * @throws ImportacionInvalidaException
     */
    protected function pedirLectura(string $contenido, string $nombre, string $extension, array $schema, string $instrucciones): array
    {
        // Edge case del spec: sin clave configurada los ficheros estructurados siguen
        // analizándose —no pasan por aquí— y los documentos se rechazan con explicación.
        if (IaTenant::apiKey() === '') {
            throw new ImportacionInvalidaException(
                "Para leer «{$nombre}» hace falta tener el asistente configurado con una clave de API. "
                .'Mientras tanto puedo importar hojas de cálculo (xlsx, xls o csv), que no la necesitan.'
            );
        }

        try {
            $respuesta = \OpenAI::client(IaTenant::apiKey())->chat()->create([
                'model' => config('ia.modelo'),
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $instrucciones],
                            $this->parteDelDocumento($contenido, $nombre, $extension),
                        ],
                    ],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'lectura_material_importable',
                        'strict' => true,
                        'schema' => $schema,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);

            throw new ImportacionInvalidaException("No se ha podido interpretar «{$nombre}». Probá de nuevo en unos instantes.");
        }

        $lectura = json_decode($respuesta->choices[0]->message->content ?? '', true);

        if (! is_array($lectura)) {
            throw new ImportacionInvalidaException("No se ha podido interpretar «{$nombre}»: la respuesta no se pudo leer.");
        }

        return $lectura;
    }

    /**
     * Una imagen viaja como `image_url` con data URI, un PDF como parte `file` con `file_data` y el
     * texto plano como texto: son los formatos que el proveedor acepta sin conversión previa.
     *
     * @return array<string, mixed>
     */
    private function parteDelDocumento(string $contenido, string $nombre, string $extension): array
    {
        if ($extension === 'txt') {
            return ['type' => 'text', 'text' => "Contenido de «{$nombre}»:\n\n".$contenido];
        }

        $base64 = base64_encode($contenido);

        if ($extension === 'pdf') {
            return [
                'type' => 'file',
                'file' => ['filename' => $nombre, 'file_data' => 'data:application/pdf;base64,'.$base64],
            ];
        }

        $mime = match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,".$base64]];
    }
}
