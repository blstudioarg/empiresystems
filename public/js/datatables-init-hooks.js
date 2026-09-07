/**
 * Ajustes que se aplican a TODOS los DataTables de la app, sin tener que repetirlos en cada uno
 * de los ~24 `plugins-init/*-datatable.init.js`.
 *
 * Va como handler delegado en `document` (y no como `$.fn.dataTable.defaults`) porque este archivo
 * se carga desde `layouts/app.blade.php`, o sea ANTES del `@stack('scripts')` donde cada vista
 * carga `jquery.dataTables.min.js`: en este punto `$.fn.dataTable` todavía no existe y tocar sus
 * defaults reventaría. Los eventos `init.dt`/`draw.dt` sí burbujean hasta el documento, así que
 * registrar el handler antes de que el plugin exista funciona igual.
 *
 * `draw.dt` además de `init.dt` porque DataTables regenera los controles del wrapper en cada
 * redibujado (paginar, buscar, `ajax.reload()`), perdiendo las clases agregadas a mano.
 */
(function ($) {
	'use strict';

	$(document).on('init.dt draw.dt', function (event, settings) {
		// La paginación es el último elemento del wrapper: su margin-bottom se suma al padding
		// del card-body y deja un hueco extra debajo de toda tabla de la app.
		$(settings.nTableWrapper).find('.dataTables_paginate').addClass('mb-0');
	});
})(jQuery);
