(function ($) {
	'use strict';

	$(function () {
		var $form = $('#menu-form');

		if (!$form.length) {
			return;
		}

		var $menuGrupos = $('#menu-grupos');
		var updateUrl = $form.attr('action');
		var csrfToken = $form.find('input[name="_token"]').val();

		function csrf() {
			return csrfToken;
		}

		function escapeHtml(texto) {
			return $('<div>').text(texto == null ? '' : texto).html();
		}

		// Convierte la clave de error del servidor ("etiquetas.clientes") al `name` del input
		// correspondiente ("etiquetas[clientes]") — el `data-error-for` del contrato usa notación
		// con punto, pero los inputs del form usan notación de array PHP.
		function nombreInputDesdeClave(clave) {
			var partes = clave.split('.');

			return partes[0] + partes.slice(1).map(function (parte) { return '[' + parte + ']'; }).join('');
		}

		function clearErrors() {
			$form.find('.is-invalid').removeClass('is-invalid');
			$form.find('[data-error-for]').text('');
		}

		function showErrors(errors) {
			clearErrors();

			$.each(errors, function (clave, mensajes) {
				$form.find('input[name="' + nombreInputDesdeClave(clave) + '"]').addClass('is-invalid');
				$form.find('[data-error-for="' + clave + '"]').text(mensajes[0]);
			});
		}

		// Confinamiento por nivel por construcción (research.md D3/FR-008a): cada `<ul>` de
		// entradas es un sortable INDEPENDIENTE, sin `connectWith` entre ellos ni con el de grupos
		// — jQuery UI devuelve por sí solo cualquier fila soltada fuera de su lista de origen.
		function initSortables() {
			$menuGrupos.sortable({
				items: '> li.menu-editor-grupo',
				handle: '.menu-editor-handle',
				distance: 5,
				placeholder: 'menu-editor-placeholder',
				forcePlaceholderSize: true,
			});

			$('.menu-editor-hijos').each(function () {
				$(this).sortable({
					handle: '.menu-editor-handle',
					distance: 5,
					placeholder: 'menu-editor-placeholder',
					forcePlaceholderSize: true,
				});
			});
		}

		function destroySortables() {
			if ($menuGrupos.hasClass('ui-sortable')) {
				$menuGrupos.sortable('destroy');
			}

			$('.menu-editor-hijos.ui-sortable').sortable('destroy');
		}

		function filaHtml(elemento, incluirToggle) {
			var toggleHtml = incluirToggle
				? '<button type="button" class="menu-editor-toggle" aria-expanded="false" title="Mostrar/ocultar entradas"><i class="fas fa-chevron-right"></i></button>'
				: '';

			return '<div class="menu-editor-fila">'
				+ '<span class="menu-editor-handle" title="Arrastrar para reordenar"><i class="fas fa-grip-vertical"></i></span>'
				+ toggleHtml
				+ '<div class="menu-editor-campo">'
				+ '<input type="text" class="form-control" name="etiquetas[' + elemento.clave + ']" value="' + escapeHtml(elemento.etiqueta) + '" maxlength="40">'
				+ '<small class="form-text text-muted">Por defecto: ' + escapeHtml(elemento.etiqueta_defecto) + '</small>'
				+ '<div class="invalid-feedback" data-error-for="etiquetas.' + elemento.clave + '"></div>'
				+ '</div>'
				+ '</div>';
		}

		// Grupos contraídos por defecto (también tras "Restaurar"): con las 26 entradas de segundo
		// nivel siempre desplegadas, reordenar los 10 grupos entre sí obligaba a scrollear una
		// lista larguísima. Contraído por defecto, solo hace falta desplegar el grupo que se va a
		// reordenar por dentro.
		function grupoHtml(grupo) {
			var tieneHijos = !!(grupo.hijos && grupo.hijos.length);
			var clase = 'menu-editor-grupo' + (tieneHijos ? ' colapsado' : '');
			var html = '<li class="' + clase + '" data-clave="' + grupo.clave + '">' + filaHtml(grupo, tieneHijos);

			if (tieneHijos) {
				html += '<ul class="menu-editor-hijos" data-clave-grupo="' + grupo.clave + '">';
				grupo.hijos.forEach(function (hijo) {
					html += '<li class="menu-editor-hijo" data-clave="' + hijo.clave + '">' + filaHtml(hijo, false) + '</li>';
				});
				html += '</ul>';
			}

			html += '</li>';

			return html;
		}

		function repintar(estructura) {
			destroySortables();
			$menuGrupos.html(estructura.map(grupoHtml).join(''));
			initSortables();
		}

		// FR-020b: nada se persiste hasta pulsar Guardar — el arrastre solo mueve el DOM. Por eso
		// `orden` se lee del DOM recién en el momento del envío, no en cada `sortstop`.
		function leerEtiquetas() {
			var etiquetas = {};

			$form.find('input[name^="etiquetas["]').each(function () {
				var match = /^etiquetas\[(.+)\]$/.exec($(this).attr('name'));

				if (match) {
					etiquetas[match[1]] = $(this).val();
				}
			});

			return etiquetas;
		}

		function leerOrden() {
			var orden = {};

			orden._raiz = $menuGrupos.find('> li.menu-editor-grupo').map(function () {
				return $(this).data('clave');
			}).get();

			$('.menu-editor-hijos').each(function () {
				var claveGrupo = $(this).data('clave-grupo');

				orden[claveGrupo] = $(this).find('> li.menu-editor-hijo').map(function () {
					return $(this).data('clave');
				}).get();
			});

			return orden;
		}

		initSortables();

		// Delegado sobre el contenedor (no sobre las filas): sigue funcionando después de
		// `repintar()`, que reemplaza el contenido de #menu-grupos pero no el propio elemento.
		$menuGrupos.on('click', '.menu-editor-toggle', function () {
			var $boton = $(this);
			var $grupo = $boton.closest('.menu-editor-grupo');

			$grupo.toggleClass('colapsado');
			$boton.attr('aria-expanded', $grupo.hasClass('colapsado') ? 'false' : 'true');
		});

		$form.on('submit', function (event) {
			event.preventDefault();

			clearErrors();

			var $submitBtn = $('#menu-guardar-btn');

			window.withButtonLoading($submitBtn, function () {
				return $.ajax({
					url: updateUrl,
					method: 'POST',
					data: {
						_token: csrf(),
						_method: 'PUT',
						etiquetas: leerEtiquetas(),
						orden: leerOrden(),
					},
					dataType: 'json',
					headers: { Accept: 'application/json' },
				});
			})
				.done(function (response) {
					window.showToast('success', response.message || 'Menú guardado correctamente.');
				})
				.fail(function (xhr) {
					if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
						showErrors(xhr.responseJSON.errors);
					} else {
						window.showToast('danger', 'No se pudo guardar el menú. Inténtalo de nuevo.');
					}
				});
		});

		$('#menu-restaurar-btn').on('click', function () {
			var url = $(this).data('restaurar-url');

			window.confirmDelete(
				'¿Restaurar el menú lateral a los valores por defecto? Se perderán los nombres y el orden personalizados.',
				function () {
					return $.ajax({
						url: url,
						method: 'POST',
						data: { _token: csrf(), _method: 'DELETE' },
						dataType: 'json',
						headers: { Accept: 'application/json' },
					})
						.done(function (response) {
							clearErrors();
							repintar(response.estructura);
							window.showToast('success', response.message || 'Menú restaurado a los valores por defecto.');
						})
						.fail(function () {
							window.showToast('danger', 'No se pudo restaurar el menú. Inténtalo de nuevo.');
						});
				},
				{ confirmLabel: 'Restaurar', confirmClass: 'btn-primary' },
			);
		});
	});
})(jQuery);
