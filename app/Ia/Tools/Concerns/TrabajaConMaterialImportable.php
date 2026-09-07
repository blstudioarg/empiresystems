<?php

namespace App\Ia\Tools\Concerns;

use App\Excel\BorradorImportacion;
use App\Excel\DefinicionImportable;
use App\Excel\RegistroDefiniciones;
use App\Exceptions\ImportacionInvalidaException;
use App\Ia\ConversacionAsistente;
use App\Services\AlmacenImportaciones;
use App\Services\ImportadorExcel;
use App\Services\InterpretadorMaterialImportable;
use App\Support\MaterialImportable;

/**
 * Lo que comparten las tres tools de importación conversacional (feature 046): resolver el borrador
 * del token acotado a empresa y persona, y armar el análisis del contrato §3.
 *
 * Aquí no se valida **nada** de negocio: eso lo hace `ImportadorExcel::analizarFilas()`, que delega
 * en el validador del alta manual. Si esta pieza empezara a decidir si una fila vale, habría dos
 * caminos de validación y se desalinearían (plan, riesgo #2).
 */
trait TrabajaConMaterialImportable
{
    /**
     * Módulos importables y el permiso de cada uno, resueltos por el contrato `DefinicionImportable`
     * (FR-010): facturas y albaranes no aparecen porque no lo implementan, no porque haya una lista
     * negra que alguien pueda olvidar de actualizar.
     *
     * @return array<string, string> módulo => permiso
     */
    public static function modulosImportables(): array
    {
        $permisos = [];

        foreach (app(RegistroDefiniciones::class)->importables() as $modulo => $definicion) {
            $permisos[$modulo] = $definicion->permiso();
        }

        return $permisos;
    }

    /**
     * Borrador del token, o `null` si no existe, caducó o es de otra persona/empresa. Los tres casos
     * se responden igual a propósito: distinguirlos confirmaría que el token existe.
     */
    protected function borradorDe(string $token): ?BorradorImportacion
    {
        $usuario = auth()->user();

        if ($usuario === null || $usuario->tenant_id === null) {
            return null;
        }

        $borrador = app(AlmacenImportaciones::class)->borrador($token, (int) $usuario->tenant_id, (int) $usuario->id);

        if ($borrador === null || ! $borrador->perteneceAlHilo(app(ConversacionAsistente::class)->idActivo())) {
            return null;
        }

        return $borrador;
    }

    protected function definicionDe(BorradorImportacion $borrador): DefinicionImportable
    {
        return app(RegistroDefiniciones::class)->resolverImportable($borrador->modulo);
    }

    /**
     * Re-verifica el permiso del módulo concreto con el módulo ya conocido (FR-021). La tool está
     * disponible para quien pueda importar *algún* módulo; esto comprueba que pueda importar *este*.
     */
    protected function puedeSobreElModulo(BorradorImportacion $borrador): bool
    {
        $permiso = self::modulosImportables()[$borrador->modulo] ?? null;

        return $permiso !== null && auth()->user()?->can($permiso) === true;
    }

    /**
     * Lee el material que todavía no se leyó y lo convierte en filas de trabajo.
     *
     * Es aquí y no en la subida donde ocurre (contrato §1) para que la persona vea el progreso en
     * la conversación en vez de mirar una barra de carga. Una vez leído, el fichero original se
     * borra: con las filas en el borrador deja de hacer falta, y conservarlo sería guardar datos
     * personales de terceros sin motivo (Principio II).
     *
     * @throws ImportacionInvalidaException
     */
    protected function consumirPendientes(BorradorImportacion $borrador): void
    {
        $pendientes = $borrador->pendientes();

        if ($pendientes === []) {
            return;
        }

        $almacen = app(AlmacenImportaciones::class);
        $definicion = $this->definicionDe($borrador);

        foreach ($pendientes as $pendiente) {
            $ruta = $almacen->rutaAbsoluta($pendiente['token']);

            if ($ruta === null) {
                $borrador->anotarNoInterpretado(["«{$pendiente['nombre']}» ya no está disponible: volvé a adjuntarlo."]);
                $borrador->descartarPendiente($pendiente['token']);

                continue;
            }

            if (MaterialImportable::esDocumento($pendiente['extension'])) {
                $lectura = app(InterpretadorMaterialImportable::class)
                    ->interpretar($definicion, $ruta, $pendiente['nombre'], $pendiente['extension']);

                $borrador->acumular($lectura['filas']);
                $borrador->anotarNoInterpretado($lectura['avisos']);
            } else {
                // Una hoja no pasa por el proveedor: la lee el importador de siempre, con las mismas
                // comprobaciones de cabeceras y de límite de filas.
                $borrador->acumular(array_map(
                    fn (array $fila) => ['datos' => $fila['datos']],
                    app(ImportadorExcel::class)->leerFilas($definicion, $ruta),
                ));
            }

            $almacen->borrar($pendiente['token']);
            $borrador->descartarPendiente($pendiente['token']);
        }
    }

    /**
     * Análisis del contrato §3. Los recuentos salen de aquí y **nunca** del modelo (research D6):
     * pedirle a un modelo que cuente filas de una lista larga es una forma conocida de equivocarse.
     *
     * @return array<string, mixed>
     */
    protected function analisisDe(BorradorImportacion $borrador): array
    {
        $definicion = $this->definicionDe($borrador);
        $analisis = app(ImportadorExcel::class)->analizarFilas($definicion, $this->filasParaAnalizar($borrador));

        $rechazadas = array_map(fn (array $r) => [
            'indice' => $r['indice'],
            // Nombrar el registro es lo que permite al asistente decir «a Acme le falta el NIF» en
            // vez de leerle números de fila a alguien que nunca vio el fichero (FR-011).
            'identificador' => $this->identificadorDe($borrador, $r['indice']),
            'motivo' => $r['motivo'],
            'campo' => $r['campo'],
        ], $analisis['rechazadas']);

        $salida = [
            'token' => $borrador->token,
            'modulo' => $borrador->modulo,
            'origen' => $borrador->origen,
            'leidas' => count($borrador->filas()),
            'validas' => count($analisis['validas']),
            'descartadas' => count($borrador->descartadas()),
            'rechazadas' => $rechazadas,
        ];

        if ($borrador->origen === MaterialImportable::ORIGEN_DOCUMENTO) {
            $salida['no_interpretado'] = $borrador->analisisOrigen() ?? [];
        }

        return $salida;
    }

    /**
     * Las filas que entran en el análisis y en la importación: siempre las vigentes del borrador
     * guardado, nunca una copia que viajara con la propuesta (FR-015). El token identifica el
     * borrador; la confirmación lo relee.
     *
     * @return array<int, array{indice: int, datos: array<string, mixed>}>
     */
    protected function filasParaAnalizar(BorradorImportacion $borrador): array
    {
        return array_map(
            fn (array $fila) => ['indice' => $fila['indice'], 'datos' => $fila['datos']],
            $borrador->filasVigentes(),
        );
    }

    /**
     * Campos por los que una persona reconoce un registro, en orden de preferencia. No vale «el
     * primer campo obligatorio»: en clientes ese es el tipo, y decir «a empresa y a empresa les
     * falta el NIF» no identifica nada.
     */
    private const CAMPOS_IDENTIFICADORES = ['nombre', 'razon_social', 'descripcion', 'referencia', 'sku', 'email'];

    /**
     * Cómo llamar a una fila en la conversación (FR-011). Si no hay nada legible, el número de
     * registro, que al menos es cierto.
     */
    protected function identificadorDe(BorradorImportacion $borrador, int $indice): string
    {
        $datos = null;

        foreach ($borrador->filas() as $fila) {
            if ($fila['indice'] === $indice) {
                $datos = $fila['datos'];
                break;
            }
        }

        $porDefecto = 'registro '.($indice + 1);

        if ($datos === null) {
            return $porDefecto;
        }

        foreach (self::CAMPOS_IDENTIFICADORES as $clave) {
            $valor = trim((string) ($datos[$clave] ?? ''));

            if ($valor !== '') {
                return $valor;
            }
        }

        return $porDefecto;
    }

    /**
     * Etiqueta legible de un campo, para poder pedirlo por su nombre ("el NIF") y no por su clave
     * interna ("aplica_recargo_equivalencia").
     */
    protected function etiquetaDeCampo(BorradorImportacion $borrador, ?string $campo): ?string
    {
        if ($campo === null) {
            return null;
        }

        foreach ($this->definicionDe($borrador)->columnas() as $columna) {
            if ($columna->clave === $campo) {
                return $columna->etiqueta;
            }
        }

        return null;
    }

    /**
     * Claves internas admitidas para corregir, con su etiqueta. Es lo que se le enseña al modelo
     * para que dicte correcciones con la clave correcta y no con la etiqueta del fichero.
     *
     * @return array<string, string>
     */
    protected function camposCorregibles(BorradorImportacion $borrador): array
    {
        $campos = [];

        foreach ($this->definicionDe($borrador)->columnas() as $columna) {
            if ($columna->importar !== null) {
                $campos[$columna->clave] = $columna->etiqueta;
            }
        }

        return $campos;
    }

    /**
     * Normaliza un valor dictado en la conversación con la misma función que usa el importador al
     * leerlo de un fichero: un "sí" escrito en el chat tiene que llegar al validador exactamente
     * igual que un "Sí" escrito en una celda.
     */
    protected function normalizarValor(BorradorImportacion $borrador, string $campo, mixed $valor): mixed
    {
        foreach ($this->definicionDe($borrador)->columnas() as $columna) {
            if ($columna->clave === $campo && $columna->importar !== null) {
                return ($columna->importar)($valor);
            }
        }

        return $valor;
    }
}
