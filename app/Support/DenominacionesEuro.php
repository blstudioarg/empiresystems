<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Billetes y monedas de euro en curso legal, para el arqueo de caja (feature 048, research D7).
 *
 * Las claves van en **céntimos enteros**: el conteo viaja como `{"5000": 3, "1": 7}` y el total se
 * suma sin pasar nunca por coma flotante. Es la cifra que decide si una caja cuadra; un residuo de
 * redondeo sería un descuadre que nadie ha cometido.
 *
 * `tono` y `metal` son solo para la bandeja de la pantalla de caja (color real de cada billete y
 * metal de cada moneda); el servidor no los usa para nada más.
 */
class DenominacionesEuro
{
    /**
     * @var list<array{centimos: int, tipo: 'billete'|'moneda', etiqueta: string, tono: string}>
     */
    private const CATALOGO = [
        ['centimos' => 50000, 'tipo' => 'billete', 'etiqueta' => '500 €', 'tono' => 'violeta'],
        ['centimos' => 20000, 'tipo' => 'billete', 'etiqueta' => '200 €', 'tono' => 'amarillo'],
        ['centimos' => 10000, 'tipo' => 'billete', 'etiqueta' => '100 €', 'tono' => 'verde'],
        ['centimos' => 5000, 'tipo' => 'billete', 'etiqueta' => '50 €', 'tono' => 'naranja'],
        ['centimos' => 2000, 'tipo' => 'billete', 'etiqueta' => '20 €', 'tono' => 'azul'],
        ['centimos' => 1000, 'tipo' => 'billete', 'etiqueta' => '10 €', 'tono' => 'rojo'],
        ['centimos' => 500, 'tipo' => 'billete', 'etiqueta' => '5 €', 'tono' => 'gris'],
        ['centimos' => 200, 'tipo' => 'moneda', 'etiqueta' => '2 €', 'tono' => 'bimetal'],
        ['centimos' => 100, 'tipo' => 'moneda', 'etiqueta' => '1 €', 'tono' => 'bimetal-inv'],
        ['centimos' => 50, 'tipo' => 'moneda', 'etiqueta' => '50 cént.', 'tono' => 'oro'],
        ['centimos' => 20, 'tipo' => 'moneda', 'etiqueta' => '20 cént.', 'tono' => 'oro'],
        ['centimos' => 10, 'tipo' => 'moneda', 'etiqueta' => '10 cént.', 'tono' => 'oro'],
        ['centimos' => 5, 'tipo' => 'moneda', 'etiqueta' => '5 cént.', 'tono' => 'cobre'],
        ['centimos' => 2, 'tipo' => 'moneda', 'etiqueta' => '2 cént.', 'tono' => 'cobre'],
        ['centimos' => 1, 'tipo' => 'moneda', 'etiqueta' => '1 cént.', 'tono' => 'cobre'],
    ];

    /** Tope por denominación: más de 9.999 billetes de una clase en un cajón no es un conteo real. */
    public const MAX_CANTIDAD = 9999;

    /**
     * @return list<int>
     */
    public static function valores(): array
    {
        return array_column(self::CATALOGO, 'centimos');
    }

    /**
     * @return list<array{centimos: int, tipo: string, etiqueta: string, tono: string}>
     */
    public static function todas(): array
    {
        return self::CATALOGO;
    }

    /**
     * @return list<array{centimos: int, tipo: string, etiqueta: string, tono: string}>
     */
    public static function billetes(): array
    {
        return array_values(array_filter(self::CATALOGO, fn (array $d) => $d['tipo'] === 'billete'));
    }

    /**
     * @return list<array{centimos: int, tipo: string, etiqueta: string, tono: string}>
     */
    public static function monedas(): array
    {
        return array_values(array_filter(self::CATALOGO, fn (array $d) => $d['tipo'] === 'moneda'));
    }

    public static function esValida(int $centimos): bool
    {
        return in_array($centimos, self::valores(), true);
    }

    /**
     * Deja el conteo en su forma canónica: claves string de céntimos, cantidades enteras, sin
     * ceros. Es lo que se persiste en `conteo_apertura`/`conteo_cierre`.
     *
     * @param  array<int|string, mixed>  $conteo
     * @return array<string, int>
     */
    public static function normalizar(array $conteo): array
    {
        $normalizado = [];

        foreach ($conteo as $centimos => $cantidad) {
            $centimos = (int) $centimos;
            $cantidad = (int) $cantidad;

            if (! self::esValida($centimos)) {
                throw new InvalidArgumentException("La denominación {$centimos} no existe.");
            }

            if ($cantidad < 0 || $cantidad > self::MAX_CANTIDAD) {
                throw new InvalidArgumentException('La cantidad de cada billete o moneda debe estar entre 0 y '.self::MAX_CANTIDAD.'.');
            }

            if ($cantidad > 0) {
                $normalizado[(string) $centimos] = $cantidad;
            }
        }

        return $normalizado;
    }

    /**
     * Total del conteo como decimal con 2 cifras (`"150.07"`), sumado en céntimos enteros.
     *
     * @param  array<int|string, mixed>  $conteo
     */
    public static function totalDesde(array $conteo): string
    {
        $centimos = 0;

        foreach (self::normalizar($conteo) as $valor => $cantidad) {
            $centimos += (int) $valor * $cantidad;
        }

        return self::aDecimal($centimos);
    }

    public static function aDecimal(int $centimos): string
    {
        $signo = $centimos < 0 ? '-' : '';
        $centimos = abs($centimos);

        return $signo.intdiv($centimos, 100).'.'.str_pad((string) ($centimos % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Importe decimal (string o número) a céntimos enteros, redondeando al céntimo.
     */
    public static function aCentimos(string|int|float|null $importe): int
    {
        return (int) round(((float) $importe) * 100);
    }
}
