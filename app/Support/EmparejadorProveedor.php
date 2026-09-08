<?php

namespace App\Support;

use App\Models\Proveedor;

/**
 * Busca a qué proveedor del catálogo corresponde el emisor leído de un documento (feature 044).
 *
 * Cascada (research D8): **NIF exacto** normalizado → **nombre ≥85 % y candidato único** → sin
 * coincidencia. Nunca crea nada: solo propone, y la decisión de dar de alta un proveedor nuevo es
 * siempre del usuario (FR-019), porque un NIF mal leído en un escaneo ensuciaría el catálogo de
 * forma permanente.
 *
 * Ante **dos** candidatos por encima del umbral devuelve "sin coincidencia" a propósito: elegir uno
 * al azar es peor que no elegir, porque el usuario puede no advertir el error.
 */
class EmparejadorProveedor
{
    private const UMBRAL_SIMILITUD = 85.0;

    /**
     * @param  array<string, mixed>|null  $emisor  Bloque `emisor` de la lectura del documento.
     * @return array{criterio: string, proveedor_id: int|null, proveedor_nombre: string|null, similitud: float|null, datos_nuevos: array<string, mixed>|null}
     */
    public function emparejar(?array $emisor): array
    {
        $emisor ??= [];
        $sinCoincidencia = [
            'criterio' => 'sin_coincidencia',
            'proveedor_id' => null,
            'proveedor_nombre' => null,
            'similitud' => null,
            'datos_nuevos' => $emisor === [] ? null : $emisor,
        ];

        // El scope global de tenant ya acota el catálogo a la empresa activa (Principio I).
        $proveedores = Proveedor::query()->get(['id', 'nombre', 'razon_social', 'nif']);

        if ($proveedores->isEmpty()) {
            return $sinCoincidencia;
        }

        $nifLeido = self::normalizarNif($emisor['nif'] ?? null);

        if ($nifLeido !== '') {
            $porNif = $proveedores->first(fn (Proveedor $p) => self::normalizarNif($p->nif) === $nifLeido);

            if ($porNif) {
                return [
                    'criterio' => 'nif',
                    'proveedor_id' => $porNif->id,
                    'proveedor_nombre' => $porNif->razon_social ?: $porNif->nombre,
                    'similitud' => null,
                    'datos_nuevos' => null,
                ];
            }
        }

        $nombreLeido = self::normalizarNombre($emisor['nombre'] ?? null);
        $razonLeida = self::normalizarNombre($emisor['razon_social'] ?? null);

        if ($nombreLeido === '' && $razonLeida === '') {
            return $sinCoincidencia;
        }

        $candidatos = [];

        foreach ($proveedores as $proveedor) {
            $similitud = 0.0;

            // Se compara contra ambos campos: el documento puede traer el nombre comercial o la
            // razón social, y el catálogo tampoco es consistente en cuál usa.
            foreach ([$proveedor->nombre, $proveedor->razon_social] as $candidato) {
                foreach ([$nombreLeido, $razonLeida] as $leido) {
                    if ($leido === '') {
                        continue;
                    }

                    $similitud = max($similitud, self::similitud($leido, self::normalizarNombre($candidato)));
                }
            }

            if ($similitud >= self::UMBRAL_SIMILITUD) {
                $candidatos[] = ['proveedor' => $proveedor, 'similitud' => $similitud];
            }
        }

        if (count($candidatos) !== 1) {
            return $sinCoincidencia;
        }

        $elegido = $candidatos[0]['proveedor'];

        return [
            'criterio' => 'nombre',
            'proveedor_id' => $elegido->id,
            'proveedor_nombre' => $elegido->razon_social ?: $elegido->nombre,
            'similitud' => round($candidatos[0]['similitud'], 1),
            'datos_nuevos' => null,
        ];
    }

    /**
     * Un NIF impreso llega con puntos, guiones y espacios según el maquetador de cada proveedor;
     * nada de eso lo diferencia.
     */
    private static function normalizarNif(?string $nif): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $nif) ?? '');
    }

    /**
     * Quita acentos, puntuación y forma societaria: "Suministros Industriales S.L." y
     * "suministros industriales" son la misma empresa.
     */
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
        $texto = preg_replace('/\b(sl|slu|sa|sau|sl u|s l|s a|sociedad limitada|sociedad anonima|scp|sc|slne|cb)\b/', ' ', $texto) ?? '';

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
