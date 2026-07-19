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

        $datosValidos = [];
        $rechazadas = [];
        $muestra = [];
        $vistos = [];

        foreach ($filas as $indice => $filaCruda) {
            $numeroFila = $indice + 2; // fila 1 = cabecera, los datos empiezan en la 2

            $filaNormalizada = [];
            foreach ($columnas as $columna) {
                // La cabecera leída del fichero está normalizada por la etiqueta (lo que el
                // usuario ve), no por la clave interna (lo que espera el FormRequest/modelo) —
                // ambas coinciden la mayoría de las veces pero no siempre (p. ej. "Recargo de
                // equivalencia" → "recargo_de_equivalencia", clave interna "aplica_recargo_...").
                $valorCrudo = $filaCruda[ColumnaExcel::normalizarCabecera($columna->etiqueta)] ?? null;
                $filaNormalizada[$columna->clave] = $columna->importar
                    ? ($columna->importar)($valorCrudo)
                    : $valorCrudo;
            }

            $validador = $definicion->validador($filaNormalizada);

            if ($validador->fails()) {
                $rechazadas[] = new FilaRechazada($numeroFila, $validador->errors()->first());

                continue;
            }

            $motivoDuplicado = $this->motivoDuplicadoEnFichero($definicion, $filaNormalizada, $numeroFila, $vistos);

            if ($motivoDuplicado !== null) {
                $rechazadas[] = new FilaRechazada($numeroFila, $motivoDuplicado);

                continue;
            }

            $datosValidos[] = $validador->validated();

            // La muestra se muestra al usuario (modal/vista de previsualización): claves con la
            // etiqueta legible (contracts/importacion.md), no la clave interna del modelo.
            $filaMuestra = [];
            foreach ($columnas as $columna) {
                $filaMuestra[$columna->etiqueta] = $filaNormalizada[$columna->clave];
            }
            $muestra[] = $filaMuestra;
        }

        return [
            'totalFilas' => count($filas),
            'datosValidos' => $datosValidos,
            'rechazadas' => $rechazadas,
            'muestra' => $muestra,
        ];
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
     * Detección de duplicados dentro del propio fichero (research.md D3), más allá de la unicidad
     * contra la base de datos que ya validó `$definicion->validador()`.
     *
     * @param  array<string, int>  &$vistos
     */
    private function motivoDuplicadoEnFichero(DefinicionImportable $definicion, array $filaNormalizada, int $numeroFila, array &$vistos): ?string
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

                return "El {$etiqueta} {$valor} está repetido en el fichero (fila {$vistos[$clave]}).";
            }

            $vistos[$clave] = $numeroFila;
        }

        return null;
    }
}
