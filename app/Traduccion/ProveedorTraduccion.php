<?php

namespace App\Traduccion;

use App\Exceptions\TraduccionNoDisponibleException;

/**
 * Servicio externo de traducción automática (feature 050, research D6). Implementación actual:
 * {@see TraductorDeepl}. La interfaz existe para poder cambiar de proveedor o simularlo en tests.
 */
interface ProveedorTraduccion
{
    /**
     * Traduce del español al idioma indicado, respetando variables `:x` y siglas no traducibles.
     *
     * @param  list<string>  $textos
     * @return list<string> traducciones en el mismo orden
     *
     * @throws TraduccionNoDisponibleException
     */
    public function traducir(array $textos, string $idioma, bool $html, ?int $timeout = null): array;

    /**
     * Deja en el proveedor el glosario vigente para el idioma (crea el de esta versión si no
     * existe y borra los antiguos). Devuelve su id, o null si no se pudo.
     *
     * @param  array<string, string>  $entradas  término español => traducción fijada
     *
     * @throws TraduccionNoDisponibleException
     */
    public function sincronizarGlosario(string $idioma, array $entradas): ?string;
}
