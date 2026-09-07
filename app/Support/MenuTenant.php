<?php

namespace App\Support;

use App\Http\Middleware\ModuloHosteleriaActivo;
use App\Models\Configuracion;

/**
 * Capa de personalización del menú lateral por tenant (feature 036, data-model.md §2-3 y §5).
 * Persiste una única fila JSON (`menu.personalizacion`, grupo `menu`) en `configuraciones`; su
 * ausencia significa "tenant sin personalizar" (FR-016). `estructura()` fusiona el catálogo
 * ({@see CatalogoMenu}) con esa personalización y es lo único que consume el sidebar.
 *
 * El servidor nunca confía en lo que llega del cliente (research.md D4): `guardar()` recorre el
 * catálogo y descarta cualquier clave que no exista en él, así que una petición manipulada no
 * puede inventar elementos ni resucitar uno retirado.
 */
class MenuTenant
{
    public const CLAVE = 'menu.personalizacion';

    /** @var array<int, list<array<string, mixed>>> memoización por request, indexada por tenant_id */
    private static array $memo = [];

    /**
     * @return list<array{clave: string, etiqueta: string, etiqueta_defecto: string, icono: ?string, ruta: ?string, permiso: ?string, hijos: array}>
     */
    public static function estructura(int $tenantId): array
    {
        if (array_key_exists($tenantId, self::$memo)) {
            return self::$memo[$tenantId];
        }

        $personalizacion = self::personalizacionGuardada($tenantId);

        $catalogo = self::podarModulosInactivos(CatalogoMenu::catalogo(), $tenantId);

        return self::$memo[$tenantId] = self::fusionar($catalogo, $personalizacion, '_raiz');
    }

    /**
     * Retira del catálogo las entradas de módulos opcionales que el tenant no tiene activos
     * (feature 038, FR-056), *antes* de fusionar la personalización. Se hace aquí y no en el
     * sidebar para que ninguna vista tenga que acordarse de la regla.
     *
     * Es solo UX: el enforcement real de acceso lo hace el middleware del módulo
     * ({@see ModuloHosteleriaActivo}).
     *
     * @param  list<array<string, mixed>>  $elementos
     * @return list<array<string, mixed>>
     */
    private static function podarModulosInactivos(array $elementos, int $tenantId): array
    {
        $activo = [
            CatalogoMenu::MODULO_HOSTELERIA => fn () => ConfigPos::hosteleriaActivo($tenantId),
        ];

        $podados = [];

        foreach ($elementos as $elemento) {
            $modulo = $elemento['modulo'] ?? null;

            if ($modulo !== null && ! (($activo[$modulo] ?? fn () => false)())) {
                continue;
            }

            if (! empty($elemento['hijos'])) {
                $elemento['hijos'] = self::podarModulosInactivos($elemento['hijos'], $tenantId);
            }

            $podados[] = $elemento;
        }

        return $podados;
    }

    /**
     * @param  array<string, string>  $etiquetas
     * @param  array<string, list<string>>  $orden
     */
    public static function guardar(int $tenantId, array $etiquetas, array $orden): void
    {
        $normalizado = self::normalizar($etiquetas, $orden);

        if (empty($normalizado['etiquetas']) && empty($normalizado['orden'])) {
            self::borrarFila($tenantId);
        } else {
            Configuracion::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'clave' => self::CLAVE],
                ['valor' => json_encode($normalizado), 'tipo' => 'json', 'grupo' => 'menu'],
            );
        }

        unset(self::$memo[$tenantId]);
    }

    public static function restaurar(int $tenantId): void
    {
        self::borrarFila($tenantId);

        unset(self::$memo[$tenantId]);
    }

    /**
     * Invalida la memoización de un tenant (mismo patrón que
     * {@see AparienciaTenant::invalidarCache()}). `guardar()`/`restaurar()` ya la invocan
     * internamente; solo hace falta llamarla a mano si la fila `configuraciones` se toca por otra
     * vía (p. ej. un test que escribe el valor JSON directo para simular datos corruptos).
     */
    public static function invalidarCache(int $tenantId): void
    {
        unset(self::$memo[$tenantId]);
    }

    private static function borrarFila(int $tenantId): void
    {
        Configuracion::query()
            ->where('tenant_id', $tenantId)
            ->where('clave', self::CLAVE)
            ->delete();
    }

    /**
     * @return array{etiquetas: array<string, string>, orden: array<string, list<string>>}
     */
    private static function personalizacionGuardada(int $tenantId): array
    {
        $valor = Configuracion::query()
            ->where('tenant_id', $tenantId)
            ->where('clave', self::CLAVE)
            ->value('valor');

        $vacio = ['etiquetas' => [], 'orden' => []];

        if ($valor === null || $valor === '') {
            return $vacio;
        }

        $decodificado = json_decode($valor, true);

        if (! is_array($decodificado)) {
            return $vacio;
        }

        return [
            'etiquetas' => is_array($decodificado['etiquetas'] ?? null) ? $decodificado['etiquetas'] : [],
            'orden' => is_array($decodificado['orden'] ?? null) ? $decodificado['orden'] : [],
        ];
    }

    /**
     * Fusión recursiva catálogo + personalización (data-model.md §3). Determinista y total: para
     * cualquier JSON guardado (vacío, corrupto o con claves inventadas) devuelve siempre una
     * estructura con exactamente los elementos del catálogo.
     *
     * @param  list<array<string, mixed>>  $elementos
     * @param  array{etiquetas: array<string, string>, orden: array<string, list<string>>}  $personalizacion
     * @return list<array<string, mixed>>
     */
    private static function fusionar(array $elementos, array $personalizacion, string $nivel): array
    {
        $ordenGuardado = $personalizacion['orden'][$nivel] ?? [];
        $ordenados = self::ordenar($elementos, is_array($ordenGuardado) ? $ordenGuardado : []);

        return array_map(function (array $elemento) use ($personalizacion) {
            $etiquetaPersonalizada = $personalizacion['etiquetas'][$elemento['clave']] ?? null;
            $etiquetaPersonalizada = is_string($etiquetaPersonalizada) ? trim($etiquetaPersonalizada) : null;
            $tieneHijos = ! empty($elemento['hijos']);

            return [
                'clave' => $elemento['clave'],
                'etiqueta' => $etiquetaPersonalizada !== null && $etiquetaPersonalizada !== ''
                    ? $etiquetaPersonalizada
                    : $elemento['etiqueta'],
                'etiqueta_defecto' => $elemento['etiqueta'],
                'icono' => $elemento['icono'],
                'ruta' => $elemento['ruta'],
                'permiso' => $elemento['permiso'],
                'hijos' => $tieneHijos
                    ? self::fusionar($elemento['hijos'], $personalizacion, $elemento['clave'])
                    : [],
            ];
        }, $ordenados);
    }

    /**
     * Ordena `$elementos` (orden por defecto del catálogo) según `$ordenGuardado`: primero los que
     * aparecen ahí en ese orden, después el resto conservando su orden relativo del catálogo
     * (FR-017). Descarta del orden guardado las claves que no pertenezcan a `$elementos` (FR-018).
     *
     * @param  list<array<string, mixed>>  $elementos
     * @param  list<mixed>  $ordenGuardado
     * @return list<array<string, mixed>>
     */
    private static function ordenar(array $elementos, array $ordenGuardado): array
    {
        $porClave = [];
        foreach ($elementos as $elemento) {
            $porClave[$elemento['clave']] = $elemento;
        }

        $resultado = [];
        foreach ($ordenGuardado as $clave) {
            if (is_string($clave) && isset($porClave[$clave])) {
                $resultado[] = $porClave[$clave];
                unset($porClave[$clave]);
            }
        }

        foreach ($elementos as $elemento) {
            if (isset($porClave[$elemento['clave']])) {
                $resultado[] = $elemento;
            }
        }

        return $resultado;
    }

    /**
     * Normaliza contra el catálogo antes de persistir (data-model.md §4, research.md D4): claves
     * desconocidas se descartan, etiquetas iguales al valor por defecto no se guardan, y un nivel
     * de `orden` idéntico al orden por defecto del catálogo tampoco se guarda — así "restaurar" y
     * "nunca personalizó nada" son literalmente el mismo estado (FR-016).
     *
     * @param  array<string, mixed>  $etiquetas
     * @param  array<string, mixed>  $orden
     * @return array{etiquetas: array<string, string>, orden: array<string, list<string>>}
     */
    private static function normalizar(array $etiquetas, array $orden): array
    {
        $planos = CatalogoMenu::planos();

        $etiquetasNormalizadas = [];
        foreach ($etiquetas as $clave => $valor) {
            if (! is_string($clave) || ! isset($planos[$clave]) || ! is_string($valor)) {
                continue;
            }

            $valor = trim($valor);

            if ($valor === '' || $valor === $planos[$clave]['etiqueta']) {
                continue;
            }

            $etiquetasNormalizadas[$clave] = $valor;
        }

        $nivelesValidos = array_merge(['_raiz'], CatalogoMenu::clavesGrupos());
        $ordenNormalizado = [];

        foreach ($orden as $nivel => $lista) {
            if (! is_string($nivel) || ! in_array($nivel, $nivelesValidos, true) || ! is_array($lista)) {
                continue;
            }

            $clavesDelNivel = $nivel === '_raiz'
                ? CatalogoMenu::clavesGrupos()
                : array_column($planos[$nivel]['hijos'] ?? [], 'clave');

            $listaFiltrada = array_values(array_filter($lista, fn ($clave) => is_string($clave) && in_array($clave, $clavesDelNivel, true)));

            if ($listaFiltrada === [] || $listaFiltrada === $clavesDelNivel) {
                continue;
            }

            $ordenNormalizado[$nivel] = $listaFiltrada;
        }

        return ['etiquetas' => $etiquetasNormalizadas, 'orden' => $ordenNormalizado];
    }
}
