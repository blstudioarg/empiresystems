<?php

namespace App\Services;

use App\Excel\ColumnaExcel;
use App\Excel\DefinicionImportable;
use App\Excel\FilaRechazada;
use App\Excel\PrevisualizacionImportacion;
use App\Excel\ResultadoImportacion;
use App\Exceptions\ImportacionInvalidaException;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Throwable;

/**
 * Previsualiza y confirma la importación de un módulo (contracts/importacion.md). Síncrono, sin
 * colas (Principio V) — igual que `ImportadorLeads`. Nunca aborta por filas inválidas: importa las
 * válidas y reporta las rechazadas (FR-016).
 */
class ImportadorExcel
{
    private const LIMITE_FILAS = 2000;

    private const TAMANO_MUESTRA = 10;

    public function __construct(private readonly AlmacenImportaciones $almacen) {}

    /**
     * Analiza el fichero sin escribir ningún dato (FR-012, FR-014).
     */
    public function previsualizar(DefinicionImportable $definicion, UploadedFile $fichero): PrevisualizacionImportacion
    {
        $token = $this->almacen->guardar($fichero);
        $ruta = $this->almacen->rutaAbsoluta($token);

        try {
            $analisis = $this->analizar($definicion, $ruta);
        } catch (ImportacionInvalidaException $e) {
            $this->almacen->borrar($token);

            throw $e;
        }

        return new PrevisualizacionImportacion(
            token: $token,
            modulo: $definicion->modulo(),
            totalFilas: $analisis['totalFilas'],
            validas: count($analisis['datosValidos']),
            rechazadas: $analisis['rechazadas'],
            muestra: array_slice($analisis['muestra'], 0, self::TAMANO_MUESTRA),
        );
    }

    /**
     * Ejecuta la importación (FR-014: solo aquí se escribe). Revalida el fichero entero desde
     * cero — no se fía del veredicto de la previsualización (research.md D2): entre previsualizar
     * y confirmar, otro usuario del tenant puede haber creado un registro que ahora choca.
     */
    public function confirmar(DefinicionImportable $definicion, string $token, int $tenantId): ResultadoImportacion
    {
        $ruta = $this->almacen->rutaAbsoluta($token);

        if ($ruta === null) {
            throw new ImportacionInvalidaException('La previsualización ha caducado. Vuelve a subir el fichero.');
        }

        try {
            $analisis = $this->analizar($definicion, $ruta);
        } catch (ImportacionInvalidaException $e) {
            $this->almacen->borrar($token);

            throw $e;
        }

        $importados = 0;
        foreach ($analisis['datosValidos'] as $datos) {
            $definicion->crear($datos, $tenantId);
            $importados++;
        }

        $this->almacen->borrar($token);

        return new ResultadoImportacion($importados, $analisis['rechazadas']);
    }

    /**
     * @return array{totalFilas: int, datosValidos: array<int, array<string, mixed>>, rechazadas: FilaRechazada[], muestra: array<int, array<string, mixed>>}
     */
    private function analizar(DefinicionImportable $definicion, string $rutaAbsoluta): array
    {
        $columnas = $definicion->columnas();
        $normalizadas = $this->leerFilas($definicion, $rutaAbsoluta);

        $analisis = $this->analizarFilas($definicion, $normalizadas, 'fichero');

        $muestra = [];
        foreach ($analisis['validas'] as $valida) {
            // La muestra se muestra al usuario (modal/vista de previsualización): claves con la
            // etiqueta legible (contracts/importacion.md), no la clave interna del modelo.
            $filaMuestra = [];
            foreach ($columnas as $columna) {
                $filaMuestra[$columna->etiqueta] = $valida['normalizada'][$columna->clave];
            }
            $muestra[] = $filaMuestra;
        }

        return [
            'totalFilas' => count($normalizadas),
            'datosValidos' => array_column($analisis['validas'], 'datos'),
            'rechazadas' => array_map(
                fn (array $r) => new FilaRechazada($r['indice'], $r['motivo']),
                $analisis['rechazadas'],
            ),
            'muestra' => $muestra,
        ];
    }

    /**
     * Lee un fichero y devuelve sus filas **ya normalizadas** a las claves internas, listas para
     * `analizarFilas()`. Es la mitad "leer" de lo que antes hacía `analizar()` de una pieza: la
     * importación conversacional necesita las filas por separado para poder corregirlas antes de
     * validarlas (feature 046, research D2).
     *
     * @return array<int, array{indice: int, datos: array<string, mixed>}>
     *
     * @throws ImportacionInvalidaException
     */
    public function leerFilas(DefinicionImportable $definicion, string $rutaAbsoluta): array
    {
        $columnas = $definicion->columnas();

        $this->comprobarCabeceras($definicion, $rutaAbsoluta, $columnas);

        try {
            $hojas = Excel::toArray(new class implements WithHeadingRow {}, $rutaAbsoluta);
        } catch (Throwable) {
            throw new ImportacionInvalidaException('No se ha podido leer el fichero. Comprueba que no esté dañado, protegido con contraseña o que el formato no sea el esperado.');
        }

        $filas = $hojas[0] ?? [];

        // Ignora filas totalmente vacías (frecuentes al final de una hoja rellenada a mano).
        $filas = array_values(array_filter(
            $filas,
            fn (array $fila) => collect($fila)->contains(fn ($v) => trim((string) ($v ?? '')) !== '')
        ));

        if (count($filas) === 0) {
            throw new ImportacionInvalidaException('El fichero no contiene ninguna fila de datos.');
        }

        if (count($filas) > self::LIMITE_FILAS) {
            throw new ImportacionInvalidaException(
                'El fichero tiene '.number_format(count($filas), 0, ',', '.').' filas de datos, más del límite de '
                .number_format(self::LIMITE_FILAS, 0, ',', '.').' por importación. Divídelo en varios ficheros más pequeños.'
            );
        }

        $normalizadas = [];

        foreach ($filas as $indice => $filaCruda) {
            $normalizadas[] = [
                'indice' => $indice + 2, // fila 1 = cabecera, los datos empiezan en la 2
                'datos' => $this->normalizarFila($columnas, $filaCruda),
            ];
        }

        return $normalizadas;
    }

    /**
     * Reindexa una fila cruda del fichero a las claves internas que esperan `validador()`/`crear()`.
     *
     * La cabecera leída del fichero está normalizada por la etiqueta (lo que el usuario ve), no por
     * la clave interna (lo que espera el FormRequest/modelo) — ambas coinciden la mayoría de las
     * veces pero no siempre (p. ej. "Recargo de equivalencia" → "recargo_de_equivalencia", clave
     * interna "aplica_recargo_equivalencia").
     *
     * @param  ColumnaExcel[]  $columnas
     * @param  array<string, mixed>  $filaCruda
     * @return array<string, mixed>
     */
    private function normalizarFila(array $columnas, array $filaCruda): array
    {
        $filaNormalizada = [];

        foreach ($columnas as $columna) {
            $valorCrudo = $filaCruda[ColumnaExcel::normalizarCabecera($columna->etiqueta)] ?? null;
            $filaNormalizada[$columna->clave] = $columna->importar
                ? ($columna->importar)($valorCrudo)
                : $valorCrudo;
        }

        return $filaNormalizada;
    }

    /**
     * Costura por filas (feature 046, research D2): valida filas **ya normalizadas** a las claves
     * internas, vengan de un fichero o del material que el asistente interpretó.
     *
     * Es el único sitio donde se decide si una fila vale, para las dos vías: la corrección
     * conversacional es imposible sobre un fichero (no se puede reescribir el `.xlsx` cuando la
     * persona dice «el NIF de Acme es B12345678»), y el material interpretado nunca fue tabular.
     * Un camino de validación propio del asistente nacería desalineado del alta manual.
     *
     * @param  array<int, array{indice: int, datos: array<string, mixed>}>  $filas
     * @param  string  $origen  Cómo nombrar el material en los motivos de rechazo ("fichero"/"material").
     * @return array{leidas: int, validas: array<int, array{indice: int, datos: array<string, mixed>, normalizada: array<string, mixed>}>, rechazadas: array<int, array{indice: int, motivo: string, campo: string|null}>}
     */
    public function analizarFilas(DefinicionImportable $definicion, array $filas, string $origen = 'material'): array
    {
        if (count($filas) > self::LIMITE_FILAS) {
            throw new ImportacionInvalidaException(
                'El '.$origen.' tiene '.number_format(count($filas), 0, ',', '.').' filas de datos, más del límite de '
                .number_format(self::LIMITE_FILAS, 0, ',', '.').' por importación. Divídelo en partes más pequeñas.'
            );
        }

        $validas = [];
        $rechazadas = [];
        $vistos = [];

        foreach ($filas as $fila) {
            $validador = $definicion->validador($fila['datos']);

            if ($validador->fails()) {
                $rechazadas[] = [
                    'indice' => $fila['indice'],
                    'motivo' => $validador->errors()->first(),
                    // Qué campo concreto falla es lo que permite al asistente pedir ese dato en vez
                    // de leerle el error al usuario (FR-011).
                    'campo' => array_key_first($validador->errors()->messages()),
                ];

                continue;
            }

            $duplicado = $this->duplicadoEnElMaterial($definicion, $fila['datos'], $fila['indice'], $vistos, $origen);

            if ($duplicado !== null) {
                $rechazadas[] = $duplicado + ['indice' => $fila['indice']];

                continue;
            }

            $validas[] = [
                'indice' => $fila['indice'],
                'datos' => $validador->validated(),
                'normalizada' => $fila['datos'],
            ];
        }

        return [
            'leidas' => count($filas),
            'validas' => $validas,
            'rechazadas' => $rechazadas,
        ];
    }

    /**
     * Importa filas ya normalizadas (feature 046). **Revalida desde cero** en vez de fiarse del
     * análisis previo: entre que el asistente analizó el material y la persona confirmó, otra
     * persona del tenant pudo crear un registro que ahora choca (FR-019). Es la misma propiedad que
     * da revalidar el fichero en la vía de la 031, aplicada al soporte nuevo.
     *
     * `crear()` fuerza el `tenant_id` (FR-020): lo que traiga el material da igual.
     *
     * @param  array<int, array{indice: int, datos: array<string, mixed>}>  $filas
     */
    public function importarFilas(DefinicionImportable $definicion, array $filas, int $tenantId): ResultadoImportacion
    {
        $analisis = $this->analizarFilas($definicion, $filas);

        $importados = 0;
        foreach ($analisis['validas'] as $valida) {
            $definicion->crear($valida['datos'], $tenantId);
            $importados++;
        }

        // Nunca aborta por filas inválidas: importa las válidas y reporta las rechazadas (FR-018).
        return new ResultadoImportacion(
            $importados,
            array_map(fn (array $r) => new FilaRechazada($r['indice'], $r['motivo']), $analisis['rechazadas']),
        );
    }

    /**
     * @param  ColumnaExcel[]  $columnas
     */
    private function comprobarCabeceras(DefinicionImportable $definicion, string $rutaAbsoluta, array $columnas): void
    {
        try {
            $cabecerasLeidas = Excel::toArray(new HeadingRowImport, $rutaAbsoluta)[0][0] ?? [];
        } catch (Throwable) {
            throw new ImportacionInvalidaException('No se ha podido leer el fichero. Comprueba que no esté dañado, protegido con contraseña o que el formato no sea el esperado.');
        }

        $cabecerasLeidas = array_map(fn ($c) => (string) $c, $cabecerasLeidas);

        $faltantes = [];
        foreach ($columnas as $columna) {
            $cabeceraEsperada = ColumnaExcel::normalizarCabecera($columna->etiqueta);

            if ($columna->obligatoria && ! in_array($cabeceraEsperada, $cabecerasLeidas, true)) {
                $faltantes[] = $columna->etiqueta;
            }
        }

        if ($faltantes !== []) {
            throw new ImportacionInvalidaException(
                'Faltan columnas obligatorias en el fichero: '.implode(', ', $faltantes).'.'
            );
        }
    }

    /**
     * Detección de duplicados dentro del propio material (research.md D3 de la 031), más allá de la
     * unicidad contra la base de datos que ya validó `$definicion->validador()`.
     *
     * Los dos casos se distinguen en el motivo a propósito (FR-007): «repetido en el fichero» y «ya
     * existe un cliente con ese NIF» piden acciones distintas de quien lo lee.
     *
     * @param  array<string, int>  &$vistos
     * @return array{motivo: string, campo: string}|null
     */
    private function duplicadoEnElMaterial(DefinicionImportable $definicion, array $filaNormalizada, int $numeroFila, array &$vistos, string $origen): ?array
    {
        foreach ($definicion->camposUnicos() as $campo) {
            $valor = $filaNormalizada[$campo] ?? null;

            if ($valor === null || trim((string) $valor) === '') {
                continue;
            }

            $clave = $campo.'::'.mb_strtolower((string) $valor);

            if (isset($vistos[$clave])) {
                $columna = collect($definicion->columnas())->firstWhere('clave', $campo);
                $etiqueta = $columna?->etiqueta ?? $campo;

                return [
                    'motivo' => "El {$etiqueta} {$valor} está repetido en el {$origen} (fila {$vistos[$clave]}).",
                    'campo' => $campo,
                ];
            }

            $vistos[$clave] = $numeroFila;
        }

        return null;
    }
}
