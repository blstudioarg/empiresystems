/**
 * Subida de la foto de perfil: elegir el archivo la guarda directamente, sin botón "Guardar"
 * (patrón del avatar del sidebar, reutilizado por cualquier vista vía el componente Blade
 * `<x-avatar-editable>`; ver docs/04-front-guidelines.md, "Cambiar la foto de perfil").
 *
 * Handler delegado en `document` para que valga por igual en el sidebar, en el perfil, o en las
 * dos a la vez cuando la misma página monta más de un editor. Al terminar se refrescan TODAS las
 * imágenes del avatar de la página (las del componente, la del header y la del listado de
 * miembros), porque la foto se ve en varios sitios a la vez y quedaría desincronizada.
 */
(function () {
	'use strict';

	function refrescarAvatares(url) {
		document
			.querySelectorAll('[data-avatar-preview], .header-media img, .products img.avatar-md')
			.forEach(function (img) {
				img.src = url;
			});
	}

	/**
	 * Spinner sobre TODOS los avatares editables de la página mientras la foto sube (equivalente
	 * al disabled+spinner obligatorio de los botones AJAX; acá el disparador es un input file, no
	 * un botón, así que no aplica `withButtonLoading`). Van todos y no solo el que se tocó porque
	 * todos muestran la misma foto y todos se van a actualizar al terminar.
	 *
	 * Los inputs se deshabilitan durante la subida para que no se encadenen dos peticiones a la
	 * vez: la segunda podría resolverse antes y dejar guardada la foto que no era.
	 */
	function marcarSubiendo(subiendo) {
		document.querySelectorAll('.avatar-editable').forEach(function (avatar) {
			avatar.classList.toggle('is-uploading', subiendo);
			avatar.setAttribute('aria-busy', subiendo ? 'true' : 'false');
		});

		document.querySelectorAll('[data-avatar-input]').forEach(function (input) {
			input.disabled = subiendo;
		});
	}

	document.addEventListener('change', function (event) {
		var input = event.target.closest('[data-avatar-input]');

		if (!input || !input.files.length) {
			return;
		}

		var form = input.closest('form');

		if (!form) {
			return;
		}

		// El FormData se arma ANTES de deshabilitar el input: un campo deshabilitado no se serializa
		// y la petición saldría sin el archivo.
		var datos = new FormData(form);

		marcarSubiendo(true);

		fetch(form.action, {
			method: 'POST',
			headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
			body: datos,
		})
			.then(function (response) {
				return response.json().then(function (data) {
					return { ok: response.ok, data: data };
				});
			})
			.then(function (resultado) {
				if (!resultado.ok) {
					var data = resultado.data;
					var mensaje = data.errors
						? Object.values(data.errors)[0][0]
						: data.message || 'No se pudo actualizar la foto.';

					window.showToast('error', mensaje);
					return;
				}

				refrescarAvatares(resultado.data.avatar_url);
				window.showToast('success', resultado.data.message);
			})
			.catch(function () {
				window.showToast('error', 'No se pudo actualizar la foto.');
			})
			.finally(function () {
				marcarSubiendo(false);

				// Sin esto, volver a elegir el mismo archivo no dispara `change` y parece que la
				// subida se ignoró (típico al reintentar tras un error de validación).
				input.value = '';
			});
	});
})();
