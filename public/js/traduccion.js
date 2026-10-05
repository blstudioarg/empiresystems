/**
 * Traducción de los textos de los JS (feature 050, contracts/traducciones.md).
 *
 * `__t('Texto en español', { nombre: 'x' })` — mismo contrato que `__()` de Laravel: la clave es el
 * propio texto en español; si hay traducción en el diccionario `window.posI18n` (inyectado solo en
 * las pantallas del POS con el idioma distinto de español) la devuelve, si no devuelve el español.
 * Las variables `:nombre` se sustituyen después de traducir, así que los datos del negocio nunca
 * se traducen.
 *
 * Se carga en todas las páginas para que `__t` exista siempre (y devuelva el español).
 */
(function (window) {
    'use strict';

    function sustituir(texto, params) {
        if (!params) {
            return texto;
        }

        // Las claves más largas primero, para que `:total` no pise a `:totalNeto` (igual que Laravel).
        Object.keys(params)
            .sort(function (a, b) { return b.length - a.length; })
            .forEach(function (clave) {
                var valor = params[clave] === null || params[clave] === undefined ? '' : String(params[clave]);
                texto = texto.split(':' + clave).join(valor);
            });

        return texto;
    }

    window.__t = function (texto, params) {
        var diccionario = window.posI18n || {};
        var traduccion = Object.prototype.hasOwnProperty.call(diccionario, texto) ? diccionario[texto] : texto;

        return sustituir(traduccion, params);
    };
})(window);
