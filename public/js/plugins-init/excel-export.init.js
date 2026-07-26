(function ($) {
	'use strict';

	function csrfToken() {
		var meta = document.querySelector('meta[name="csrf-token"]');
		return meta ? meta.getAttribute('content') : '';
	}

	function nombreDesdeCabecera(header, fallback) {
		var match = /filename="?([^"]+)"?/.exec(header || '');
		return match ? match[1] : fallback;
	}

	/**
	 * Recoge los IDs de las filas visibles del DataTable (búsqueda/filtros aplicados,
	 * docs/04-front-guidelines.md) y hace POST al endpoint de exportación del módulo,
	 * disparando la descarga del .xlsx devuelto.
	 *
	 * @param {Object} opciones
	 * @param {string} opciones.boton Selector del botón "Exportar".
	 * @param {string} opciones.url Endpoint POST /exportar/{modulo}.
	 * @param {Function} [opciones.table] Devuelve la instancia de DataTable ya inicializada
	 *        (tablas client-side, con el dataset completo cargado: clientes/artículos/facturas/
	 *        albaranes/leads).
	 * @param {Function} [opciones.ids] Devuelve (o resuelve, vía Promise) el array de IDs a
	 *        exportar. Para tablas server-side (p. ej. logs de actividad) donde el navegador nunca
	 *        tiene cargadas todas las filas que matchean el filtro — `table` no sirve ahí, ver
	 *        `logs-datatable.init.js`. Exactamente una de las dos opciones, nunca ambas.
	 */
	window.initExportacionExcel = function (opciones) {
		var $boton = $(opciones.boton);

		if (!$boton.length) {
			return;
		}

		$boton.on('click', function () {
			var idsPromesa = opciones.ids
				? Promise.resolve(opciones.ids())
				: Promise.resolve(recogerIdsDeTabla(opciones.table()));

			idsPromesa.then(function (ids) {
				exportar(ids);
			});
		});

		function recogerIdsDeTabla(table) {
			if (!table) {
				return [];
			}

			return table
				.rows({ search: 'applied' })
				.data()
				.toArray()
				.map(function (fila) {
					return fila.id;
				});
		}

		function exportar(ids) {
			if (ids.length === 0) {
				window.showToast('warning', 'No hay filas para exportar con los filtros actuales.');

				return;
			}

			var textoOriginal = $boton.text();
			$boton.prop('disabled', true).text('Exportando...');

			fetch(opciones.url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': csrfToken(),
					Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				},
				body: JSON.stringify({ ids: ids }),
			})
				.then(function (response) {
					if (!response.ok) {
						throw new Error('export-failed');
					}

					var nombre = nombreDesdeCabecera(response.headers.get('Content-Disposition'), 'exportacion.xlsx');

					return response.blob().then(function (blob) {
						return { blob: blob, nombre: nombre };
					});
				})
				.then(function (resultado) {
					var url = window.URL.createObjectURL(resultado.blob);
					var enlace = document.createElement('a');
					enlace.href = url;
					enlace.download = resultado.nombre;
					document.body.appendChild(enlace);
					enlace.click();
					enlace.remove();
					window.URL.revokeObjectURL(url);
				})
				.catch(function () {
					window.showToast('danger', 'No se pudo generar el fichero de exportación.');
				})
				.finally(function () {
					$boton.prop('disabled', false).text(textoOriginal);
				});
		}
	};
})(jQuery);
