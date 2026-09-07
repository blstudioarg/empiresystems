<?php

namespace App\Excel;

use App\Exceptions\ImportacionInvalidaException;
use App\Support\MaterialImportable;

/**
 * Importación en curso del asistente (feature 046, data-model.md).
 *
 * **No es una tabla**: vive como un fichero junto al material, en el almacén de importaciones de la
 * 031, y se lo lleva la misma purga diaria. Una importación a medias no aporta nada una vez
 * terminada, y persistirla obligaría a un plazo de retención propio para un dato que sobra
 * (Principio II, minimización).
 *
 * Guarda **filas de trabajo**, no el fichero original: sobre un `.xlsx` no se puede aplicar «el NIF
 * de Acme es B12345678» sin reescribirlo, y el material interpretado nunca fue tabular (research
 * D4). Las claves de `datos` son las internas de {@see ColumnaExcel}, las mismas que esperan
 * `validador()` y `crear()`, así que el borrador entra en el pipeline existente sin traducción.
 *
 * `estado` y `motivo` no viven aquí a propósito: se recalculan en cada análisis. Guardarlos sería
 * arriesgarse a enseñar un veredicto viejo.
 */
class BorradorImportacion
{
    /**
     * @param  array<int, array{indice: int, datos: array<string, mixed>, leido: array<string, bool>}>  $filas
     * @param  int[]  $descartadas
     * @param  array<int, array{indice: int, campo: string, valor: mixed}>  $correcciones
     * @param  array<int, string>|null  $analisisOrigen  Qué no se pudo leer del documento interpretado.
     * @param  array<int, array{token: string, nombre: string, extension: string}>  $pendientes  Material subido y todavía no leído.
     */
    public function __construct(
        public readonly string $token,
        public readonly int $tenantId,
        public readonly int $userId,
        public readonly ?int $conversacionId,
        public readonly string $modulo,
        public string $origen,
        private array $filas = [],
        private array $descartadas = [],
        private array $correcciones = [],
        private ?array $analisisOrigen = null,
        private array $pendientes = [],
        public readonly ?string $creadoEn = null,
    ) {
        $this->filas = array_values($this->filas);
    }

    /**
     * @return array<int, array{indice: int, datos: array<string, mixed>, leido: array<string, bool>}>
     */
    public function filas(): array
    {
        return $this->filas;
    }

    /**
     * Las filas que entran en la importación: todas menos las que la persona dejó fuera (FR-013).
     *
     * @return array<int, array{indice: int, datos: array<string, mixed>, leido: array<string, bool>}>
     */
    public function filasVigentes(): array
    {
        return array_values(array_filter(
            $this->filas,
            fn (array $fila) => ! in_array($fila['indice'], $this->descartadas, true),
        ));
    }

    /**
     * Si el material sigue siendo del hilo en el que se está conversando.
     *
     * Cambiar de conversación —o empezar una nueva— deja el material fuera de juego, igual que
     * invalida una propuesta pendiente (FR-023 de la 045): el asistente del hilo nuevo no debe
     * poder importar un fichero del que ahí no se habló nunca. No hace falta borrar nada: deja de
     * ser accesible y se lo lleva la purga diaria.
     *
     * Un borrador sin hilo (el material se adjuntó antes de escribir el primer mensaje, que es
     * cuando nace la conversación) lo adopta el hilo actual: es el caso normal, no un cambio de
     * contexto.
     */
    public function perteneceAlHilo(?int $conversacionId): bool
    {
        return $this->conversacionId === null || $this->conversacionId === $conversacionId;
    }

    /** @return int[] */
    public function descartadas(): array
    {
        return array_values($this->descartadas);
    }

    /** @return array<int, array{indice: int, campo: string, valor: mixed}> */
    public function correcciones(): array
    {
        return $this->correcciones;
    }

    /** @return array<int, string>|null */
    public function analisisOrigen(): ?array
    {
        return $this->analisisOrigen;
    }

    /**
     * Aplica un dato que la persona dictó en la conversación (FR-012).
     *
     * Falla explícitamente si el índice o el campo no existen. Ignorarlos en silencio sería lo peor
     * que puede pasar aquí: el asistente diría «hecho», el dato no estaría, y nadie lo notaría
     * hasta ver el maestro mal (contrato §4).
     *
     * @throws ImportacionInvalidaException
     */
    public function corregir(int $indice, string $campo, mixed $valor): void
    {
        $posicion = $this->posicionDe($indice);

        if (! array_key_exists($campo, $this->filas[$posicion]['datos'])) {
            throw new ImportacionInvalidaException(
                "El campo «{$campo}» no existe en los datos de {$this->modulo}, así que no se puede corregir."
            );
        }

        $this->filas[$posicion]['datos'][$campo] = $valor;

        // Deja de ser «el documento no lo dice» y pasa a ser un dato aportado por la persona: si no
        // se marcase, el análisis seguiría reportando el hueco que la persona ya rellenó (FR-009).
        if ($this->filas[$posicion]['leido'] !== []) {
            $this->filas[$posicion]['leido'][$campo] = true;
        }

        $this->correcciones[] = ['indice' => $indice, 'campo' => $campo, 'valor' => $valor];
    }

    /**
     * Deja una fila fuera de la importación (FR-013). No la borra: sigue en el borrador para poder
     * explicar qué se dejó fuera, y para que los índices que la persona ya usó en la conversación
     * sigan apuntando a la misma fila.
     *
     * @throws ImportacionInvalidaException
     */
    public function descartar(int $indice): void
    {
        $this->posicionDe($indice);

        if (! in_array($indice, $this->descartadas, true)) {
            $this->descartadas[] = $indice;
        }
    }

    /**
     * Añade filas de otro documento a la misma importación (US3, escenario 4) continuando la
     * numeración: reutilizar índices haría que una corrección dictada antes apuntase a otra fila.
     *
     * @param  array<int, array{datos: array<string, mixed>, leido?: array<string, bool>}>  $filas
     */
    public function acumular(array $filas): void
    {
        $siguiente = $this->filas === [] ? 0 : max(array_column($this->filas, 'indice')) + 1;

        foreach ($filas as $fila) {
            $this->filas[] = [
                'indice' => $siguiente++,
                'datos' => $fila['datos'],
                'leido' => $fila['leido'] ?? [],
            ];
        }
    }

    /**
     * Material subido y todavía sin leer.
     *
     * Subir no analiza (contrato §1): deja el fichero disponible y anota que está ahí, y es el
     * asistente quien dispara la lectura para que la persona vea el progreso en la conversación.
     * Guardar el puntero permite además acumular varios documentos en la misma importación (US3,
     * escenario 4) sin releer —ni volver a pagar— lo ya interpretado.
     *
     * @return array<int, array{token: string, nombre: string, extension: string}>
     */
    public function pendientes(): array
    {
        return array_values($this->pendientes);
    }

    public function anotarPendiente(string $token, string $nombre, string $extension): void
    {
        $this->pendientes[] = ['token' => $token, 'nombre' => $nombre, 'extension' => $extension];

        // Acumular un PDF sobre una importación que empezó con un Excel la convierte en mixta, y a
        // efectos de lo que hay que contarle a la persona eso es «documento»: es el único origen que
        // puede traer cosas sin interpretar de las que avisar.
        if (MaterialImportable::esDocumento($extension)) {
            $this->origen = MaterialImportable::ORIGEN_DOCUMENTO;
        }
    }

    /**
     * Da por leído un material. Se quita de uno en uno, no en bloque al final: si la lectura de un
     * documento falla a mitad de lote, lo ya leído no se pierde y solo se reintenta lo que quedó.
     */
    public function descartarPendiente(string $token): void
    {
        $this->pendientes = array_values(array_filter(
            $this->pendientes,
            static fn (array $p): bool => $p['token'] !== $token,
        ));
    }

    /**
     * @param  array<int, string>  $avisos
     */
    public function anotarNoInterpretado(array $avisos): void
    {
        $this->analisisOrigen = array_values(array_unique([...($this->analisisOrigen ?? []), ...$avisos]));
    }

    /**
     * @throws ImportacionInvalidaException
     */
    private function posicionDe(int $indice): int
    {
        foreach ($this->filas as $posicion => $fila) {
            if ($fila['indice'] === $indice) {
                return $posicion;
            }
        }

        throw new ImportacionInvalidaException(
            "No hay ninguna fila con el número {$indice} en esta importación."
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'conversacion_id' => $this->conversacionId,
            'modulo' => $this->modulo,
            'origen' => $this->origen,
            'filas' => $this->filas,
            'descartadas' => $this->descartadas(),
            'correcciones' => $this->correcciones,
            'analisis_origen' => $this->analisisOrigen,
            'pendientes' => $this->pendientes(),
            'creado_en' => $this->creadoEn,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function desdeArray(array $datos): self
    {
        return new self(
            token: (string) $datos['token'],
            tenantId: (int) $datos['tenant_id'],
            userId: (int) $datos['user_id'],
            conversacionId: isset($datos['conversacion_id']) ? (int) $datos['conversacion_id'] : null,
            modulo: (string) $datos['modulo'],
            origen: (string) $datos['origen'],
            filas: $datos['filas'] ?? [],
            descartadas: $datos['descartadas'] ?? [],
            correcciones: $datos['correcciones'] ?? [],
            analisisOrigen: $datos['analisis_origen'] ?? null,
            pendientes: $datos['pendientes'] ?? [],
            creadoEn: $datos['creado_en'] ?? null,
        );
    }
}
