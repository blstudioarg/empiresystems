<?php

namespace App\Support;

use App\Enums\TipoArticulo;
use App\Models\Articulo;

/**
 * Busca a qué artículo del catálogo corresponde una línea leída de un documento (feature 044).
 *
 * Cascada (research D9): **SKU exacto** normalizado → **nombre ≥88 % y candidato único** → línea
 * libre. Una línea sin artículo es un resultado **válido** (portes, un servicio suelto): no mueve
 * stock y no pasa nada.
 *
 * El umbral es más alto que el de proveedor a propósito: emparejar mal un proveedor se ve enseguida
 * en la ficha; emparejar mal un artículo mete stock en el sitio equivocado y no se nota hasta el
 * recuento. **Nunca crea artículos** (FR-025).
 */
class EmparejadorArticulo
{
    private const UMBRAL_SIMILITUD = 88.0;

    /**
     * @param  array<string, mixed>  $linea  Línea cruda de la lectura del documento.
     * @return array{criterio: string, articulo_id: int|null, articulo_nombre: string|null, mueve_stock: bool, similitud: float|null}
     */
    public function emparejar(array $linea): array
    {
        $sinCoincidencia = [
            'criterio' => 'sin_coincidencia',
            'articulo_id' => null,
            'articulo_nombre' => null,
            'mueve_stock' => false,
            'similitud' => null,
        ];

        // El scope global acota al tenant activo; aquí solo se descartan los dados de baja.
        $articulos = Articulo::query()
            ->where('activo', true)
            ->get(['id', 'sku', 'nombre', 'tipo', 'gestion_stock']);

        if ($articulos->isEmpty()) {
            return $sinCoincidencia;
        }

        $skuLeido = self::normalizarSku($linea['referencia'] ?? null);

        if ($skuLeido !== '') {
            $porSku = $articulos->first(fn (Articulo $a) => self::normalizarSku($a->sku) === $skuLeido);

            if ($porSku) {
                return $this->resultado($porSku, 'referencia', null);
            }
        }

        $conceptoLeido = self::normalizarNombre($linea['concepto'] ?? null);

        if ($conceptoLeido === '') {
            return $sinCoincidencia;
        }

        $candidatos = [];

        foreach ($articulos as $articulo) {
            $similitud = self::similitud($conceptoLeido, self::normalizarNombre($articulo->nombre));

            if ($similitud >= self::UMBRAL_SIMILITUD) {
                $candidatos[] = ['articulo' => $articulo, 'similitud' => $similitud];
            }
        }

        if (count($candidatos) !== 1) {
            return $sinCoincidencia;
        }

        return $this->resultado($candidatos[0]['articulo'], 'nombre', round($candidatos[0]['similitud'], 1));
    }

    /**
     * @return array{criterio: string, articulo_id: int|null, articulo_nombre: string|null, mueve_stock: bool, similitud: float|null}
     */
    private function resultado(Articulo $articulo, string $criterio, ?float $similitud): array
    {
        return [
            'criterio' => $criterio,
            'articulo_id' => $articulo->id,
            'articulo_nombre' => $articulo->nombre,
            // Solo un producto con gestión de stock mueve inventario al confirmar la compra.
            'mueve_stock' => $articulo->tipo === TipoArticulo::Producto && (bool) $articulo->gestion_stock,
            'similitud' => $similitud,
        ];
    }

    /**
     * Un mismo SKU se imprime con guiones, espacios o pegado según el proveedor.
     */
    private static function normalizarSku(?string $sku): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $sku) ?? '');
    }

    private static function normalizarNombre(?string $nombre): string
    {
        $texto = mb_strtolower(trim((string) $nombre));

        if ($texto === '') {
            return '';
        }

        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c',
        ]);

        $texto = preg_replace('/[^a-z0-9 ]/', ' ', $texto) ?? '';

        return trim(preg_replace('/\s+/', ' ', $texto) ?? '');
    }

    private static function similitud(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 100.0;
        }

        similar_text($a, $b, $porcentaje);

        return (float) $porcentaje;
    }
}
