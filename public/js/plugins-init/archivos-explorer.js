(function ($) {
	'use strict';

	$(function () {
		var state = window.archivosState || {};
		var $dropZone = $('#archivos-drop-zone');
		var $explorer = $('#archivos-explorer');
		var $lista = $('#archivos-lista');
		var $skeleton = $('#archivos-skeleton');
		var $vacio = $('#archivos-vacio');
		var $breadcrumbsNav = $('#archivos-breadcrumbs-nav');
		var $breadcrumbs = $('#archivos-breadcrumbs');
		var $buscadorInfo = $('#archivos-busqueda-info');
		var $buscador = $('#archivos-buscador');
		var $btnLimpiarBusqueda = $('#btn-limpiar-busqueda');
		var $input = $('#archivo-input');

		if (!$explorer.length) {
			return;
		}

		var VISTA_KEY = 'archivos-vista';
		var vistaActual = localStorage.getItem(VISTA_KEY) || 'grid';
		var ultimaRespuesta = null;

		function csrfToken() {
			return $('meta[name="csrf-token"]').attr('content');
		}

		function iconoPorExtension(extension) {
			var ext = (extension || '').toLowerCase();
			if (ext === 'pdf') return 'fa-file-pdf text-danger';
			if (['jpg', 'jpeg', 'png', 'webp', 'gif'].indexOf(ext) !== -1) return 'fa-file-image text-info';
			if (['docx', 'odt'].indexOf(ext) !== -1) return 'fa-file-word text-primary';
			if (['xlsx', 'ods', 'csv'].indexOf(ext) !== -1) return 'fa-file-excel text-success';
			if (['pptx', 'odp'].indexOf(ext) !== -1) return 'fa-file-powerpoint text-warning';
			return 'fa-file text-secondary';
		}

		function formatoTamano(bytes) {
			if (bytes < 1024) return bytes + ' B';
			if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
			return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
		}

		// Ubicación del ítem cuando viene de un resultado de búsqueda global (puede estar en
		// cualquier nivel del árbol, no solo en la carpeta actual).
		function rutaGridHtml(item) {
			return item.ruta
				? '<div class="text-muted text-truncate" style="font-size:0.7rem" title="' + item.ruta + '"><i class="fas fa-folder-open me-1"></i>' + item.ruta + '</div>'
				: '';
		}

		function rutaFilaHtml(item) {
			return item.ruta
				? ' <span class="text-muted small">— <i class="fas fa-folder-open me-1"></i>' + item.ruta + '</span>'
				: '';
		}

		function accionesHtml(tipo) {
			return (
				'<div class="archivo-acciones' + (tipo === 'fila' ? '' : ' position-absolute top-0 end-0') + ' p-1">' +
					'<button type="button" class="btn btn-sm btn-light btn-renombrar" title="Renombrar"><i class="fas fa-pen"></i></button> ' +
					'<button type="button" class="btn btn-sm btn-light btn-mover" title="Mover"><i class="fas fa-arrows-alt"></i></button> ' +
					'<button type="button" class="btn btn-sm btn-light btn-eliminar" title="Eliminar"><i class="fas fa-trash text-danger"></i></button>' +
				'</div>'
			);
		}

		function abrirArchivo(archivo) {
			if (archivo.tiene_preview) {
				abrirPreview(archivo);
			} else {
				window.location = archivo.descargar_url;
			}
		}

		function abrirPreview(archivo) {
			var $body = $('#preview-modal-body');
			$('#preview-modal-titulo').text(archivo.nombre);
			$('#preview-modal-descargar').attr('href', archivo.descargar_url);

			if (archivo.mime && archivo.mime.indexOf('image/') === 0) {
				$body.html('<img src="' + archivo.preview_url + '" class="img-fluid" alt="' + archivo.nombre + '">');
			} else {
				$body.html('<iframe src="' + archivo.preview_url + '" style="width:100%;height:70vh;border:0"></iframe>');
			}

			$('#previewModal').modal('show');
		}

		// --- Render: vista rejilla ---

		function renderCarpetaGrid(carpeta) {
			var $el = $(
				'<div class="col-xl-2 col-lg-3 col-sm-4 col-6 archivo-item" data-tipo="carpeta" data-id="' + carpeta.id + '" data-nombre="' + carpeta.nombre + '" data-update-url="' + carpeta.update_url + '" data-delete-url="' + carpeta.delete_url + '" draggable="true">' +
					'<div class="card same-card h-100 archivo-card position-relative" role="button">' +
						'<div class="card-body text-center py-4">' +
							'<i class="fas fa-folder fa-3x text-warning mb-2"></i>' +
							'<div class="small text-truncate" title="' + carpeta.nombre + '">' + carpeta.nombre + '</div>' +
							rutaGridHtml(carpeta) +
						'</div>' +
						accionesHtml() +
					'</div>' +
				'</div>'
			);

			$el.find('.card-body').on('click', function () {
				navegarA(carpeta.id);
			});

			// Mover una carpeta con el botón dedicado (selector de destino) no aplica: se mueve
			// arrastrándola y soltándola sobre otra carpeta o sobre las migas de pan.
			$el.find('.btn-mover').remove();

			return $el;
		}

		function miniaturaOIcono(archivo) {
			var esImagen = archivo.mime && archivo.mime.indexOf('image/') === 0;

			if (esImagen) {
				return '<img src="' + archivo.preview_url + '" class="rounded mb-2" style="width:48px;height:48px;object-fit:cover" alt="' + archivo.nombre + '" draggable="false">';
			}

			return '<i class="fas ' + iconoPorExtension(archivo.extension) + ' fa-3x mb-2"></i>';
		}

		function renderArchivoGrid(archivo) {
			var $el = $(
				'<div class="col-xl-2 col-lg-3 col-sm-4 col-6 archivo-item" data-tipo="archivo" data-id="' + archivo.id + '" data-nombre="' + archivo.nombre + '" data-update-url="' + archivo.update_url + '" data-delete-url="' + archivo.delete_url + '" draggable="true">' +
					'<div class="card same-card h-100 archivo-card position-relative" role="button">' +
						'<div class="card-body text-center py-4">' +
							miniaturaOIcono(archivo) +
							'<div class="small text-truncate" title="' + archivo.nombre + '">' + archivo.nombre + '</div>' +
							'<div class="text-muted" style="font-size:0.75rem">' + formatoTamano(archivo.tamano) + '</div>' +
							rutaGridHtml(archivo) +
						'</div>' +
						accionesHtml() +
					'</div>' +
				'</div>'
			);

			$el.find('.card-body').on('click', function () {
				abrirArchivo(archivo);
			});

			return $el;
		}

		// --- Render: vista lista ---

		function renderCarpetaFila(carpeta) {
			var $el = $(
				'<div class="list-group-item d-flex align-items-center archivo-item archivo-fila" data-tipo="carpeta" data-id="' + carpeta.id + '" data-nombre="' + carpeta.nombre + '" data-update-url="' + carpeta.update_url + '" data-delete-url="' + carpeta.delete_url + '" role="button" draggable="true">' +
					'<i class="fas fa-folder text-warning me-3"></i>' +
					'<div class="flex-grow-1 text-truncate">' + carpeta.nombre + rutaFilaHtml(carpeta) + '</div>' +
					accionesHtml('fila') +
				'</div>'
			);

			$el.on('click', function (event) {
				if ($(event.target).closest('.archivo-acciones').length) return;
				navegarA(carpeta.id);
			});

			$el.find('.btn-mover').remove();

			return $el;
		}

		function renderArchivoFila(archivo) {
			var icono = iconoPorExtension(archivo.extension);

			var $el = $(
				'<div class="list-group-item d-flex align-items-center archivo-item archivo-fila" data-tipo="archivo" data-id="' + archivo.id + '" data-nombre="' + archivo.nombre + '" data-update-url="' + archivo.update_url + '" data-delete-url="' + archivo.delete_url + '" role="button" draggable="true">' +
					'<i class="fas ' + icono + ' me-3"></i>' +
					'<div class="flex-grow-1 text-truncate">' + archivo.nombre + rutaFilaHtml(archivo) + '</div>' +
					'<div class="text-muted small me-3" style="width:80px">' + formatoTamano(archivo.tamano) + '</div>' +
					'<div class="text-muted small me-3 d-none d-md-block" style="width:140px">' + (archivo.subido_por || '—') + '</div>' +
					accionesHtml('fila') +
				'</div>'
			);

			$el.on('click', function (event) {
				if ($(event.target).closest('.archivo-acciones').length) return;
				abrirArchivo(archivo);
			});

			return $el;
		}

		// --- Breadcrumbs ---

		function renderBreadcrumbs(breadcrumbs) {
			$breadcrumbs.empty();
			$breadcrumbs.append('<li class="breadcrumb-item"><a href="javascript:void(0)" data-id=""><i class="fas fa-home me-1"></i>Raíz</a></li>');

			(breadcrumbs || []).forEach(function (item) {
				$breadcrumbs.append('<li class="breadcrumb-item"><a href="javascript:void(0)" data-id="' + item.id + '">' + item.nombre + '</a></li>');
			});

			$breadcrumbs.find('li:last-child a').addClass('text-dark').removeAttr('href');
		}

		// --- Toggle de vista (rejilla/lista), persistido ---

		function aplicarVista() {
			$('.btn-vista').removeClass('active').filter('[data-vista="' + vistaActual + '"]').addClass('active');
			$explorer.toggleClass('d-none', vistaActual !== 'grid');
			$lista.toggleClass('d-none', vistaActual !== 'lista');
		}

		$('.btn-vista').on('click', function () {
			vistaActual = $(this).data('vista');
			localStorage.setItem(VISTA_KEY, vistaActual);
			aplicarVista();
			if (ultimaRespuesta) {
				renderNivel(ultimaRespuesta);
			}
		});

		// --- Carga y render de nivel ---

		function renderNivel(response) {
			$explorer.empty();
			$lista.empty();

			(response.carpetas || []).forEach(function (carpeta) {
				$explorer.append(renderCarpetaGrid(carpeta));
				$lista.append(renderCarpetaFila(carpeta));
			});

			(response.data || []).forEach(function (archivo) {
				$explorer.append(renderArchivoGrid(archivo));
				$lista.append(renderArchivoFila(archivo));
			});

			var total = (response.carpetas || []).length + (response.data || []).length;
			$vacio.toggleClass('d-none', total > 0);
			aplicarVista();
			actualizarMetricas(response.totales);
		}

		function actualizarMetricas(totales) {
			if (!totales) return;

			$('[data-metric="archivos"]').text(totales.archivos);
			$('[data-metric="carpetas"]').text(totales.carpetas);
			$('[data-metric="espacio"]').text((totales.espacio_bytes / 1024 / 1024).toFixed(1) + ' MB');
		}

		function cargar(params) {
			$skeleton.removeClass('d-none');
			$explorer.addClass('d-none');
			$lista.addClass('d-none');
			$vacio.addClass('d-none');

			return $.ajax({
				url: state.indexUrl,
				method: 'GET',
				data: params,
				dataType: 'json',
				headers: { Accept: 'application/json' },
			}).done(function (response) {
				state.carpetaActual = response.carpeta_actual;
				ultimaRespuesta = response;
				renderNivel(response);

				if (response.buscando) {
					var total = (response.carpetas || []).length + (response.data || []).length;
					$breadcrumbsNav.addClass('d-none');
					$buscadorInfo.removeClass('d-none').text(
						total > 0
							? total + ' resultado(s) para "' + response.termino + '"'
							: 'Sin resultados para "' + response.termino + '"'
					);
				} else {
					$breadcrumbsNav.removeClass('d-none');
					$buscadorInfo.addClass('d-none');
					renderBreadcrumbs(response.breadcrumbs);
				}
			}).fail(function () {
				window.showToast('danger', 'No se pudo cargar el contenido.');
			}).always(function () {
				$skeleton.addClass('d-none');
			});
		}

		function cargarNivel(carpetaId) {
			return cargar(carpetaId ? { carpeta: carpetaId } : {});
		}

		function buscar(termino) {
			return cargar({ q: termino });
		}

		function navegarA(carpetaId) {
			$buscador.val('');
			$btnLimpiarBusqueda.addClass('d-none');
			cargarNivel(carpetaId || null);
		}

		var debounceBusqueda = null;

		$buscador.on('input', function () {
			var termino = $(this).val().trim();
			$btnLimpiarBusqueda.toggleClass('d-none', termino === '');

			clearTimeout(debounceBusqueda);
			debounceBusqueda = setTimeout(function () {
				if (termino === '') {
					cargarNivel(state.carpetaActual || null);
				} else {
					buscar(termino);
				}
			}, 300);
		});

		$btnLimpiarBusqueda.on('click', function () {
			$buscador.val('');
			$(this).addClass('d-none');
			cargarNivel(state.carpetaActual || null);
		});

		function subirArchivo(file) {
			var formData = new FormData();
			formData.append('archivo', file);
			if (state.carpetaActual) {
				formData.append('carpeta_id', state.carpetaActual);
			}

			$.ajax({
				url: state.storeUrl,
				method: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				dataType: 'json',
				headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
			}).done(function () {
				window.showToast('success', 'Archivo subido correctamente.');
				cargarNivel(state.carpetaActual || null);
			}).fail(function (xhr) {
				var mensaje = (xhr.responseJSON && xhr.responseJSON.message)
					|| (xhr.responseJSON && xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0])
					|| 'No se pudo subir el archivo.';
				window.showToast('danger', mensaje);
			});
		}

		$('#btn-subir-archivo, #btn-subir-desde-vacio').on('click', function () {
			$input.trigger('click');
		});

		var $nuevaCarpetaModal = $('#nuevaCarpetaModal');
		var $nuevaCarpetaForm = $('#nueva-carpeta-form');

		$nuevaCarpetaForm.on('submit', function (event) {
			event.preventDefault();

			var $submitBtn = $nuevaCarpetaForm.find('button[type="submit"]');
			var nombre = $('#nueva_carpeta_nombre').val();

			window.withButtonLoading($submitBtn, function () {
				return $.ajax({
					url: state.carpetasStoreUrl,
					method: 'POST',
					data: { nombre: nombre, parent_id: state.carpetaActual },
					dataType: 'json',
					headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
				});
			}).done(function () {
				$nuevaCarpetaModal.modal('hide');
				$nuevaCarpetaForm[0].reset();
				$nuevaCarpetaForm.find('[data-error-for]').text('');
				window.showToast('success', 'Carpeta creada correctamente.');
				cargarNivel(state.carpetaActual || null);
			}).fail(function (xhr) {
				if (xhr.status === 422) {
					var errors = xhr.responseJSON.errors || {};
					$nuevaCarpetaForm.find('[data-error-for="nombre"]').text((errors.nombre || [])[0] || '');
				} else {
					window.showToast('danger', 'No se pudo crear la carpeta.');
				}
			});
		});

		$input.on('change', function (event) {
			Array.prototype.forEach.call(event.target.files, subirArchivo);
			$input.val('');
		});

		// --- Arrastrar un archivo o una carpeta dentro de otra carpeta (mover) ---
		//
		// Archivos y carpetas son arrastrables (draggable="true" en los render*). Una carpeta no
		// puede soltarse sobre sí misma ni sobre una de sus propias subcarpetas (el backend valida
		// el ciclo; ver UpdateCarpetaRequest); tampoco se resalta como destino válido mientras se
		// arrastra sobre sí misma. Mientras `itemArrastrado` esté seteado, el drop en el fondo del
		// explorador (subida de archivos del SO) se ignora — es un ítem propio que se está
		// moviendo, no un fichero externo.
		var itemArrastrado = null;

		function moverItem(item, carpetaDestinoId) {
			if (String(item.origenId ?? '') === String(carpetaDestinoId ?? '')) {
				return; // Ya está en esa carpeta.
			}

			if (item.tipo === 'carpeta' && String(item.id) === String(carpetaDestinoId ?? '')) {
				return; // No se puede mover dentro de sí misma.
			}

			var datos = { _method: 'PUT' };
			datos[item.tipo === 'carpeta' ? 'parent_id' : 'carpeta_id'] = carpetaDestinoId;

			$.ajax({
				url: item.updateUrl,
				method: 'POST',
				data: datos,
				dataType: 'json',
				headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
			}).done(function () {
				window.showToast('success', '"' + item.nombre + '" movido correctamente.');
				cargarNivel(state.carpetaActual || null);
			}).fail(function (xhr) {
				var mensaje = (xhr.responseJSON && xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0])
					|| (xhr.responseJSON && xhr.responseJSON.message)
					|| 'No se pudo mover.';
				window.showToast('danger', mensaje);
			});
		}

		$dropZone.on('dragstart', '.archivo-item', function (event) {
			var $item = $(this);
			itemArrastrado = {
				id: $item.data('id'),
				tipo: $item.data('tipo'),
				nombre: $item.data('nombre'),
				updateUrl: $item.data('update-url'),
				origenId: state.carpetaActual || null,
			};
			$item.addClass('archivo-arrastrando');
			// Firefox exige al menos un setData para permitir el drag.
			event.originalEvent.dataTransfer.effectAllowed = 'move';
			event.originalEvent.dataTransfer.setData('text/plain', $item.data('nombre'));
		});

		$dropZone.on('dragend', '.archivo-item', function () {
			$(this).removeClass('archivo-arrastrando');
			itemArrastrado = null;
			$('.archivo-drop-target').removeClass('archivo-drop-target');
		});

		// Carpetas como destino de drop (de archivos o de otras carpetas).
		$dropZone.on('dragenter dragover', '.archivo-item[data-tipo="carpeta"]', function (event) {
			if (!itemArrastrado) return;
			if (itemArrastrado.tipo === 'carpeta' && String(itemArrastrado.id) === String($(this).data('id'))) return;

			event.preventDefault();
			event.stopPropagation();
			$(this).addClass('archivo-drop-target');
		});

		$dropZone.on('dragleave', '.archivo-item[data-tipo="carpeta"]', function (event) {
			event.stopPropagation();
			$(this).removeClass('archivo-drop-target');
		});

		$dropZone.on('drop', '.archivo-item[data-tipo="carpeta"]', function (event) {
			if (!itemArrastrado) return;
			event.preventDefault();
			event.stopPropagation();
			$(this).removeClass('archivo-drop-target');
			moverItem(itemArrastrado, $(this).data('id'));
		});

		// Migas de pan como destino de drop (subir de nivel / ir a la raíz arrastrando).
		$breadcrumbs.on('dragenter dragover', 'li', function (event) {
			if (!itemArrastrado) return;
			event.preventDefault();
			$(this).addClass('archivo-drop-target');
		});

		$breadcrumbs.on('dragleave', 'li', function () {
			$(this).removeClass('archivo-drop-target');
		});

		$breadcrumbs.on('drop', 'li', function (event) {
			if (!itemArrastrado) return;
			event.preventDefault();
			$(this).removeClass('archivo-drop-target');
			moverItem(itemArrastrado, $(this).find('a').data('id') || null);
		});

		$dropZone.on('dragover', function (event) {
			event.preventDefault();
			if (!itemArrastrado) {
				$dropZone.addClass('archivos-dragover');
			}
		});

		$dropZone.on('dragleave drop', function () {
			$dropZone.removeClass('archivos-dragover');
		});

		$dropZone.on('drop', function (event) {
			event.preventDefault();

			if (itemArrastrado) {
				return;
			}

			var files = event.originalEvent.dataTransfer.files;
			Array.prototype.forEach.call(files, subirArchivo);
		});

		$breadcrumbs.on('click', 'a', function () {
			var id = $(this).data('id');
			navegarA(id || null);
		});

		// --- Renombrar (archivos y carpetas) ---
		var $renombrarModal = $('#renombrarModal');
		var $renombrarForm = $('#renombrar-form');
		var renombrarObjetivo = null;

		$dropZone.on('click', '.btn-renombrar', function (event) {
			event.stopPropagation();
			var $item = $(this).closest('.archivo-item');
			renombrarObjetivo = { id: $item.data('id'), tipo: $item.data('tipo'), updateUrl: $item.data('update-url') };
			$('#renombrar_nombre').val($item.data('nombre'));
			$renombrarForm.find('[data-error-for]').text('');
			$renombrarModal.modal('show');
		});

		$renombrarForm.on('submit', function (event) {
			event.preventDefault();
			if (!renombrarObjetivo) return;

			var $submitBtn = $renombrarForm.find('button[type="submit"]');
			var nombre = $('#renombrar_nombre').val();

			window.withButtonLoading($submitBtn, function () {
				return $.ajax({
					url: renombrarObjetivo.updateUrl,
					method: 'POST',
					data: { _method: 'PUT', nombre: nombre },
					dataType: 'json',
					headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
				});
			}).done(function () {
				$renombrarModal.modal('hide');
				window.showToast('success', 'Renombrado correctamente.');
				cargarNivel(state.carpetaActual || null);
			}).fail(function (xhr) {
				if (xhr.status === 422) {
					var errors = xhr.responseJSON.errors || {};
					$renombrarForm.find('[data-error-for="nombre"]').text((errors.nombre || [])[0] || '');
				} else {
					window.showToast('danger', 'No se pudo renombrar.');
				}
			});
		});

		// --- Eliminar (archivos y carpetas, confirmación reforzada para carpetas con contenido) ---
		$dropZone.on('click', '.btn-eliminar', function (event) {
			event.stopPropagation();
			var $item = $(this).closest('.archivo-item');
			var tipo = $item.data('tipo');
			var deleteUrl = $item.data('delete-url');
			var nombre = $item.data('nombre');

			function eliminar() {
				return $.ajax({
					url: deleteUrl,
					method: 'POST',
					data: { _method: 'DELETE' },
					dataType: 'json',
					headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
				}).done(function () {
					window.showToast('success', 'Eliminado correctamente.');
					cargarNivel(state.carpetaActual || null);
				}).fail(function () {
					window.showToast('danger', 'No se pudo eliminar.');
				});
			}

			if (tipo === 'carpeta') {
				// Confirmación reforzada (FR-018): se consulta el contenido directo antes de pedir confirmación.
				$.ajax({
					url: state.indexUrl,
					method: 'GET',
					data: { carpeta: $item.data('id') },
					dataType: 'json',
					headers: { Accept: 'application/json' },
				}).done(function (response) {
					var total = (response.carpetas || []).length + (response.data || []).length;
					var mensaje = total > 0
						? '"' + nombre + '" contiene ' + total + ' elemento(s) directos. Se eliminará la carpeta y TODO su contenido (subcarpetas y archivos) de forma permanente. ¿Continuar?'
						: '¿Eliminar la carpeta "' + nombre + '"?';

					window.confirmDelete(mensaje, eliminar);
				});
			} else {
				window.confirmDelete('¿Eliminar el archivo "' + nombre + '"?', eliminar);
			}
		});

		// --- Mover archivo: navegador de carpetas dentro del modal ---
		var $moverModal = $('#moverModal');
		var $moverLista = $('#mover-lista');
		var $moverBreadcrumbs = $('#mover-breadcrumbs');
		var moverArchivoUrl = null;
		var moverCarpetaDestino = null;

		function cargarNivelMover(carpetaId) {
			$.ajax({
				url: state.indexUrl,
				method: 'GET',
				data: carpetaId ? { carpeta: carpetaId } : {},
				dataType: 'json',
				headers: { Accept: 'application/json' },
			}).done(function (response) {
				moverCarpetaDestino = response.carpeta_actual || null;
				$moverLista.empty();

				(response.carpetas || []).forEach(function (carpeta) {
					$('<a href="javascript:void(0)" class="list-group-item list-group-item-action"><i class="fas fa-folder text-warning me-2"></i>' + carpeta.nombre + '</a>')
						.on('click', function () { cargarNivelMover(carpeta.id); })
						.appendTo($moverLista);
				});

				if (!response.carpetas || response.carpetas.length === 0) {
					$moverLista.append('<div class="text-muted small p-2">No hay subcarpetas aquí.</div>');
				}

				$moverBreadcrumbs.empty();
				$moverBreadcrumbs.append('<li class="breadcrumb-item"><a href="javascript:void(0)" data-id="">Raíz</a></li>');
				(response.breadcrumbs || []).forEach(function (item) {
					$moverBreadcrumbs.append('<li class="breadcrumb-item"><a href="javascript:void(0)" data-id="' + item.id + '">' + item.nombre + '</a></li>');
				});
				$moverBreadcrumbs.find('li:last-child a').addClass('text-dark').removeAttr('href');
			});
		}

		$moverBreadcrumbs.on('click', 'a', function () {
			cargarNivelMover($(this).data('id') || null);
		});

		$dropZone.on('click', '.btn-mover', function (event) {
			event.stopPropagation();
			var $item = $(this).closest('.archivo-item');
			moverArchivoUrl = $item.data('update-url');
			cargarNivelMover(null);
			$moverModal.modal('show');
		});

		$('#btn-mover-aqui').on('click', function () {
			if (!moverArchivoUrl) return;

			var $btn = $(this);

			window.withButtonLoading($btn, function () {
				return $.ajax({
					url: moverArchivoUrl,
					method: 'POST',
					data: { _method: 'PUT', carpeta_id: moverCarpetaDestino },
					dataType: 'json',
					headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
				});
			}).done(function () {
				$moverModal.modal('hide');
				window.showToast('success', 'Archivo movido correctamente.');
				cargarNivel(state.carpetaActual || null);
			}).fail(function () {
				window.showToast('danger', 'No se pudo mover el archivo.');
			});
		});

		aplicarVista();
		cargarNivel(state.carpetaActual || null);
	});
})(jQuery);
