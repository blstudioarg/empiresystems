<?php

namespace App\Ia;

use App\Models\User;

/**
 * Sugerencias del estado vacío del panel (feature 046, US4, research D8).
 *
 * Catálogo en servidor, agrupado por categoría y **filtrado por los permisos de la persona**: es lo
 * mismo que ya hace `CatalogoTools::paraUsuario`, y evita ofrecer algo que al pulsarlo respondería
 * «no tenés permiso» (FR-027).
 *
 * Textos fijos a propósito: generarlos con IA costaría una llamada en cada apertura del panel para
 * un beneficio nulo.
 */
class SugerenciasAsistente
{
    /**
     * Categorías, en el orden en que se muestran. Cada sugerencia declara el permiso que hace falta
     * para poder llevarla a cabo; `null` = no exige ninguno.
     *
     * @var array<int, array{clave: string, etiqueta: string, sugerencias: array<int, array{texto: string, permiso: string|null}>}>
     */
    private const CATALOGO = [
        [
            'clave' => 'importar',
            'etiqueta' => 'Importar',
            'sugerencias' => [
                ['texto' => 'Necesito importar clientes desde un Excel', 'permiso' => 'ver-clientes'],
                ['texto' => 'Tengo un PDF con proveedores', 'permiso' => 'ver-proveedores'],
                ['texto' => 'Quiero cargar mi catálogo de artículos', 'permiso' => 'ver-articulos'],
            ],
        ],
        [
            'clave' => 'consultar',
            'etiqueta' => 'Consultar',
            'sugerencias' => [
                ['texto' => '¿Cuántas facturas emití este mes?', 'permiso' => 'ver-facturas'],
                ['texto' => '¿Qué clientes me deben dinero?', 'permiso' => 'ver-cobros'],
                ['texto' => 'Buscame el artículo más vendido', 'permiso' => 'ver-articulos'],
            ],
        ],
        [
            'clave' => 'crear',
            'etiqueta' => 'Crear',
            'sugerencias' => [
                ['texto' => 'Dá de alta un cliente nuevo', 'permiso' => 'ver-clientes'],
                ['texto' => 'Preparame un presupuesto', 'permiso' => 'ver-presupuestos'],
            ],
        ],
        [
            'clave' => 'aprender',
            'etiqueta' => 'Aprender',
            'sugerencias' => [
                ['texto' => '¿Cómo funciona Verifactu?', 'permiso' => null],
                ['texto' => '¿Qué puedo pedirte?', 'permiso' => null],
            ],
        ],
    ];

    /**
     * Categorías con al menos una sugerencia que la persona pueda ejecutar. Una categoría que se
     * queda sin sugerencias no se muestra vacía: desaparece.
     *
     * @return array<int, array{clave: string, etiqueta: string, sugerencias: array<int, string>}>
     */
    public function paraUsuario(User $usuario): array
    {
        $categorias = [];

        foreach (self::CATALOGO as $categoria) {
            $textos = [];

            foreach ($categoria['sugerencias'] as $sugerencia) {
                if ($sugerencia['permiso'] === null || $usuario->can($sugerencia['permiso'])) {
                    $textos[] = $sugerencia['texto'];
                }
            }

            if ($textos === []) {
                continue;
            }

            $categorias[] = [
                'clave' => $categoria['clave'],
                'etiqueta' => $categoria['etiqueta'],
                'sugerencias' => $textos,
            ];
        }

        return $categorias;
    }
}
