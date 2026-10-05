<?php

namespace App\Traduccion;

use App\Exceptions\TraduccionNoDisponibleException;
use App\Models\Traduccion;
use App\Models\TraduccionCorreccion;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Memoria de traducción (feature 050, research D4): registra los textos pendientes, los traduce
 * por lotes con el proveedor y sirve el diccionario efectivo de un ámbito para el JS.
 *
 * Nunca lanza por culpa del proveedor: un fallo deja los textos pendientes con su `intentos` y
 * `ultimo_error`, y el POS los sigue mostrando en español (FR-013).
 *
 * Singleton por request: además guarda las claves que `__()` no encontró en un request traducido,
 * para registrarlas y traducirlas **después** de enviar la respuesta (respaldo de primer uso).
 */
class MemoriaTraducciones
{
    /** @var array<string, array<string, true>> idioma => [texto => true] */
    private array $ausentes = [];

    public function __construct(private readonly ProveedorTraduccion $proveedor) {}

    /**
     * Inserta como pendientes los textos que no existan todavía (por `idioma, hash`) y marca
     * `vista_en` en los que sí. Devuelve cuántos son nuevos.
     *
     * @param  list<string>  $textos
     */
    public function registrarPendientes(array $textos, string $ambito, string $idioma): int
    {
        $porHash = [];
        foreach ($textos as $texto) {
            if (trim($texto) !== '') {
                $porHash[Traduccion::hashDe($texto)] = $texto;
            }
        }

        $nuevas = 0;
        $ahora = now();

        foreach (array_chunk($porHash, 200, true) as $lote) {
            $existentes = Traduccion::query()
                ->where('idioma', $idioma)
                ->whereIn('hash', array_keys($lote))
                ->pluck('hash')
                ->all();

            if ($existentes !== []) {
                Traduccion::query()->where('idioma', $idioma)->whereIn('hash', $existentes)->update(['vista_en' => $ahora]);
            }

            $filas = [];
            foreach (array_diff_key($lote, array_flip($existentes)) as $hash => $texto) {
                $filas[] = [
                    'idioma' => $idioma,
                    'hash' => $hash,
                    'texto' => $texto,
                    'ambito' => $ambito,
                    'es_html' => self::esHtml($texto),
                    'traduccion' => null,
                    'estado' => Traduccion::ESTADO_PENDIENTE,
                    'intentos' => 0,
                    'vista_en' => $ahora,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }

            if ($filas !== []) {
                $nuevas += DB::table('traducciones')->insertOrIgnore($filas);
            }
        }

        return $nuevas;
    }

    /**
     * Traduce las filas pendientes (y las `error` con intentos disponibles) del idioma.
     *
     * @param  list<string>|null  $hashes  limitar a estos textos (respaldo de primer uso)
     * @return array{traducidas: int, errores: int, pendientes: int, caracteres: int, fallo: ?string}
     */
    public function traducirPendientes(string $idioma, ?int $limite = null, ?int $timeout = null, ?array $hashes = null): array
    {
        $resumen = ['traducidas' => 0, 'errores' => 0, 'pendientes' => 0, 'caracteres' => 0, 'fallo' => null];

        $filas = Traduccion::query()
            ->where('idioma', $idioma)
            ->whereIn('estado', [Traduccion::ESTADO_PENDIENTE, Traduccion::ESTADO_ERROR])
            ->where('intentos', '<', (int) config('traduccion.max_intentos', 5))
            ->when($hashes !== null, fn ($q) => $q->whereIn('hash', $hashes))
            ->orderBy('id')
            ->when($limite, fn ($q) => $q->limit($limite))
            ->get();

        // Los textos que son exactamente un término del glosario no gastan cupo (research D7).
        $glosario = $this->glosarioNormalizado($idioma);
        $filas = $filas->reject(function (Traduccion $fila) use ($glosario, &$resumen) {
            $termino = $glosario[self::normalizarTermino($fila->texto)] ?? null;

            if ($termino === null) {
                return false;
            }

            $this->marcarTraducida($fila, $termino);
            $resumen['traducidas']++;

            return true;
        });

        $lote = max(1, (int) config('traduccion.lote', 50));

        foreach ($filas->groupBy(fn (Traduccion $f) => $f->es_html ? 'html' : 'texto') as $tipo => $grupo) {
            foreach ($grupo->chunk($lote) as $trozo) {
                $trozo = $trozo->values();

                try {
                    $traducciones = $this->proveedor->traducir($trozo->pluck('texto')->all(), $idioma, $tipo === 'html', $timeout);
                } catch (TraduccionNoDisponibleException $e) {
                    // Se corta aquí: si el servicio cayó o se agotó el cupo, seguir solo gasta tiempo.
                    $this->marcarFallo($trozo->all(), $e->getMessage());
                    $resumen['fallo'] = $e->getMessage();
                    $resumen['pendientes'] = $this->contarPendientes($idioma);

                    return $resumen;
                }

                foreach ($trozo as $i => $fila) {
                    $traduccion = $traducciones[$i] ?? '';
                    $resumen['caracteres'] += mb_strlen($fila->texto);

                    if ($traduccion !== '' && self::conservaVariables($fila->texto, $traduccion)) {
                        $this->marcarTraducida($fila, $traduccion);
                        $resumen['traducidas']++;
                    } else {
                        $fila->forceFill([
                            'estado' => Traduccion::ESTADO_ERROR,
                            'intentos' => $fila->intentos + 1,
                            'ultimo_error' => 'La traducción no conserva todas las variables.',
                        ])->save();
                        $resumen['errores']++;
                    }
                }
            }
        }

        $resumen['pendientes'] = $this->contarPendientes($idioma);

        return $resumen;
    }

    /**
     * Diccionario `texto => traducción` efectivo del ámbito para el tenant activo, para el
     * `__t()` de los JS (sin los bloques HTML de las guías, que solo usa Blade).
     *
     * @return array<string, string>
     */
    public function diccionario(string $ambito, string $idioma): array
    {
        $diccionario = Traduccion::query()
            ->traducidas()
            ->where('idioma', $idioma)
            ->where('ambito', $ambito)
            ->where('es_html', false)
            ->pluck('traduccion', 'texto')
            ->all();

        if (tenant() !== null) {
            foreach (TraduccionCorreccion::query()->where('idioma', $idioma)->pluck('traduccion', 'texto') as $texto => $traduccion) {
                if (array_key_exists($texto, $diccionario)) {
                    $diccionario[$texto] = $traduccion;
                }
            }
        }

        return $diccionario;
    }

    /**
     * Vuelve a dejar pendientes las traducciones automáticas del ámbito, para retraducirlas (p. ej.
     * tras cambiar el glosario). Las correcciones de los tenants no se tocan: son otra tabla.
     */
    public function marcarParaRetraducir(string $ambito, string $idioma): int
    {
        return Traduccion::query()
            ->where('idioma', $idioma)
            ->where('ambito', $ambito)
            ->update(['estado' => Traduccion::ESTADO_PENDIENTE, 'intentos' => 0, 'ultimo_error' => null]);
    }

    /** Cuántos textos se intentarían traducir ahora (pendientes o con error y con intentos disponibles). */
    public function contarPorTraducir(string $idioma): int
    {
        return Traduccion::query()
            ->where('idioma', $idioma)
            ->whereIn('estado', [Traduccion::ESTADO_PENDIENTE, Traduccion::ESTADO_ERROR])
            ->where('intentos', '<', (int) config('traduccion.max_intentos', 5))
            ->count();
    }

    /** Cuántos textos del ámbito siguen sin traducción para el idioma (aviso en Configuración). */
    public function contarPendientes(string $idioma): int
    {
        return Traduccion::query()->where('idioma', $idioma)->where('estado', '!=', Traduccion::ESTADO_TRADUCIDA)->count();
    }

    /** Apunta una clave que `__()` no encontró en un request traducido (respaldo de primer uso). */
    public function apuntarAusente(string $texto, string $idioma): void
    {
        $this->ausentes[$idioma][$texto] = true;
    }

    /**
     * Registra y traduce las claves ausentes del request. Corre en `terminating`, después de
     * enviar la respuesta: el usuario nunca espera a la API (SC-003). Nunca lanza.
     */
    public function procesarAusentes(): void
    {
        $ausentes = $this->ausentes;
        $this->ausentes = [];

        foreach ($ausentes as $idioma => $textos) {
            try {
                $textos = array_keys($textos);
                $this->registrarPendientes($textos, 'pos', $idioma);
                $this->traducirPendientes(
                    $idioma,
                    timeout: (int) config('traduccion.timeout_respaldo', 5),
                    hashes: array_map([Traduccion::class, 'hashDe'], $textos),
                );
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** @return array<string, string> término normalizado => traducción */
    private function glosarioNormalizado(string $idioma): array
    {
        $resultado = [];
        foreach ((array) config("traduccion.glosario.{$idioma}", []) as $origen => $destino) {
            $resultado[self::normalizarTermino((string) $origen)] = (string) $destino;
        }

        return $resultado;
    }

    /** Sin distinguir mayúsculas («Mesa» = «mesa» = «MESA»). */
    private static function normalizarTermino(string $texto): string
    {
        return mb_strtolower(trim($texto));
    }

    public static function esHtml(string $texto): bool
    {
        return (bool) preg_match('/<\/?[a-z][a-z0-9]*(\s[^>]*)?\/?>/i', $texto);
    }

    /** La traducción contiene todas las variables `:x` del texto original (FR-014). */
    public static function conservaVariables(string $original, string $traduccion): bool
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $original, $variables);

        foreach (array_unique($variables[1]) as $variable) {
            if (! preg_match('/:'.preg_quote($variable, '/').'(?![A-Za-z0-9_])/', $traduccion)) {
                return false;
            }
        }

        return true;
    }

    private function marcarTraducida(Traduccion $fila, string $traduccion): void
    {
        $fila->forceFill([
            'traduccion' => $traduccion,
            'estado' => Traduccion::ESTADO_TRADUCIDA,
            'ultimo_error' => null,
            'traducida_en' => now(),
        ])->save();
    }

    /** @param  list<Traduccion>  $filas */
    private function marcarFallo(array $filas, string $motivo): void
    {
        Traduccion::query()
            ->whereIn('id', array_map(fn (Traduccion $f) => $f->id, $filas))
            ->update([
                'intentos' => DB::raw('intentos + 1'),
                'ultimo_error' => mb_substr($motivo, 0, 255),
                'updated_at' => now(),
            ]);
    }
}
