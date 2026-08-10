(function () {
	var datos = window.panelSuperAdminState;

	if (datos && typeof window.Chart !== 'undefined') {
		var canvas = document.getElementById('chart-altas-tenants');

		if (canvas) {
			new Chart(canvas, {
				type: 'bar',
				data: {
					labels: datos.serie_altas.map(function (p) { return p.etiqueta; }),
					datasets: [{
						label: 'Altas de tenants',
						data: datos.serie_altas.map(function (p) { return p.valor; }),
						backgroundColor: '#1D69D6',
					}],
				},
				options: {
					responsive: true,
					scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
				},
			});
		}
	}

	document.querySelectorAll('.panel-super-admin-fila').forEach(function (fila) {
		fila.addEventListener('click', function () {
			window.location.href = fila.dataset.href;
		});
	});
})();
