<?php

namespace App\Ia\Tools;

use App\Excel\BorradorImportacion;
use App\Excel\FilaRechazada;
use App\Exceptions\ImportacionInvalidaException;
use App\Ia\Tools\Concerns\TrabajaConMaterialImportable;
use App\Services\AlmacenImportaciones;
use App\Services\ImportadorExcel;
use App\Support\MaterialImportable;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

/**
 * Tool de escritura: importa el material ya acordado (feature 046, contrato §5).
 *
 * Se propone como **una sola acción** —una tarjeta, no doscientas (research D7)— con el ojo al
 * detalle que abre la tabla de todos los campos, y con el resultado del análisis en la caja de
 * estado del modal, que se dejó preparada en la 045 justamente para esto.
 *
 * La propuesta lleva el token y **no una copia de las filas** (FR-015): al confirmar se relee el
 * borrador vigente, así que se importa siempre la última versión acordada y nunca un análisis
 * intermedio. Y se revalida contra la base de datos en ese momento (FR-019): entre el análisis y la
 * confirmación, otra persona del tenant pudo crear un registro que ahora choca.
 */
class ImportarMaterial extends ToolAsistente
{
    use TrabajaConMaterialImportable;

    public function nombre(): string
    {
        return 'importar_material';
    }

    public function descripcion(): string
    {
        return 'Importa los registros válidos del material adjunto. Requiere confirmación de la persona. '
            .'Proponela solo cuando ya analizaste el material y la persona sabe qué se va a importar y qué queda fuera.';
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

    public function permisosAlternativos(): array
    {
        return array_values(array_diff(array_values(self::modulosImportables()), [$this->permisoRequerido()]));
    }

    public function esLectura(): bool
    {
        return false;
    }

    public function proponer(array $parametros): array
    {
        $token = (string) ($parametros['token'] ?? '');
        $borrador = $this->exigirBorrador($token);

        try {
            $this->consumirPendientes($borrador);
            app(AlmacenImportaciones::class)->guardarBorrador($borrador);
            $analisis = $this->analisisDe($borrador);
        } catch (ImportacionInvalidaException $e) {
            throw ValidationException::withMessages(['material' => $e->getMessage()]);
        }

        if ($analisis['validas'] === 0) {
            throw ValidationException::withMessages([
                'material' => 'No hay ningún registro válido que importar. Corregí o descartá lo que falla antes de proponer la importación.',
            ]);
        }

        $etiqueta = $this->definicionDe($borrador)->etiquetaModulo();

        return [
            'resumen' => "Importar {$analisis['validas']} {$etiqueta}",
            // Solo el token: la fuente de verdad es el borrador guardado, que se relee al confirmar.
            'parametros' => ['token' => $borrador->token],
            'url' => $this->urlDelModulo($borrador->modulo),
            // Todos los campos de cada registro, para poder verificarlos antes de confirmar
            // (FR-017): un resumen de una línea esconde justo lo que hay que mirar.
            'detalle' => $this->detalleDe($borrador),
            'analisis' => $this->resumenDelAnalisis($analisis),
        ];
    }

    public function ejecutar(array $parametros): array
    {
        $borrador = $this->exigirBorrador((string) ($parametros['token'] ?? ''));
        $definicion = $this->definicionDe($borrador);

        try {
            $resultado = app(ImportadorExcel::class)->importarFilas(
                $definicion,
                $this->filasParaAnalizar($borrador),
                (int) tenant()->id,
            );
        } catch (ImportacionInvalidaException $e) {
            throw ValidationException::withMessages(['material' => $e->getMessage()]);
        }

        // El material ya cumplió su función: es dato personal de terceros y no se conserva
        // (Principio II, minimización).
        $almacen = app(AlmacenImportaciones::class);
        foreach ($borrador->pendientes() as $pendiente) {
            $almacen->borrar($pendiente['token']);
        }
        $almacen->borrarBorrador($borrador->token);

        $etiqueta = $definicion->etiquetaModulo();

        return [
            'id' => null,
            'entidad_tipo' => $definicion->entidadLog(),
            'mensaje' => "Se importaron {$resultado->importados} {$etiqueta}.",
            'url' => $this->urlDelModulo($borrador->modulo),
            'descripcion' => "Importó {$resultado->importados} {$etiqueta} desde el asistente",
            // Nunca aborta por filas inválidas: importa las válidas y reporta las rechazadas
            // (FR-018). Se informa fila a fila porque «2 no se pudieron importar» sin decir cuáles
            // obliga a rehacer el trabajo entero.
            // Se nombra el registro, no el número de fila: quien lee esto puede no haber visto
            // nunca el fichero, y «fila 7» no le dice nada.
            'rechazadas' => array_map(
                fn (FilaRechazada $f) => $this->identificadorDe($borrador, $f->fila).': '.$f->motivo,
                $resultado->rechazadas,
            ),
        ];
    }

    /**
     * @throws ValidationException
     */
    private function exigirBorrador(string $token): BorradorImportacion
    {
        $borrador = $this->borradorDe($token);

        if ($borrador === null) {
            throw ValidationException::withMessages([
                'material' => 'No encuentro ese material. Puede haber caducado: pedile a la persona que lo vuelva a adjuntar.',
            ]);
        }

        // Se re-comprueba en la confirmación y no solo al proponer (FR-021): entre una cosa y la
        // otra le pueden haber quitado el permiso.
        if (! $this->puedeSobreElModulo($borrador)) {
            throw ValidationException::withMessages([
                'material' => 'La persona ya no tiene permiso para importar '.$borrador->modulo.'.',
            ]);
        }

        return $borrador;
    }

    /**
     * Una fila por registro a importar, con las etiquetas legibles del módulo.
     *
     * @return array<int, array{resumen: string, campos: array<int, array{etiqueta: string, valor: string}>}>
     */
    private function detalleDe(BorradorImportacion $borrador): array
    {
        $columnas = $this->definicionDe($borrador)->columnas();
        $detalle = [];

        foreach ($borrador->filasVigentes() as $fila) {
            $campos = [];

            foreach ($columnas as $columna) {
                if ($columna->importar === null) {
                    continue;
                }

                $valor = $fila['datos'][$columna->clave] ?? null;

                if (is_bool($valor)) {
                    $valor = $valor ? 'Sí' : 'No';
                }

                $campos[] = [
                    'etiqueta' => $columna->etiqueta,
                    // Un hueco se muestra como hueco: que falte el NIF es justo lo que hay que ver.
                    'valor' => ($valor === null || $valor === '') ? '—' : (string) $valor,
                ];
            }

            $detalle[] = [
                'resumen' => $this->identificadorDe($borrador, $fila['indice']),
                'campos' => $campos,
            ];
        }

        return $detalle;
    }

    /**
     * Lo que se pinta en la caja de estado del modal de detalle (FR-017).
     *
     * @param  array<string, mixed>  $analisis
     */
    private function resumenDelAnalisis(array $analisis): string
    {
        $origen = $analisis['origen'] === MaterialImportable::ORIGEN_DOCUMENTO
            ? 'Documento interpretado'
            : 'Hoja de cálculo';

        $partes = [
            $origen,
            $analisis['leidas'].' leídas',
            $analisis['validas'].' válidas',
            $analisis['descartadas'].' descartadas',
            count($analisis['rechazadas']).' con errores',
        ];

        $texto = implode(' · ', $partes).'.';

        if (($analisis['no_interpretado'] ?? []) !== []) {
            $texto .= ' No se pudo leer: '.implode(' ', $analisis['no_interpretado']);
        }

        return $texto;
    }

    private function urlDelModulo(string $modulo): ?string
    {
        return Route::has("{$modulo}.index") ? route("{$modulo}.index") : null;
    }
}
