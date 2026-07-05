(function () {
	if (typeof window.dashboardData === 'undefined') {
		return;
	}

	var datos = window.dashboardData;

	var serieDiv = document.getElementById('morris-serie-facturacion');
	if (serieDiv && typeof window.Morris !== 'undefined') {
		Morris.Line({
			element: 'morris-serie-facturacion',
			data: datos.serie_facturacion_12_meses,
			xkey: 'etiqueta',
			ykeys: ['facturado'],
			labels: ['Facturado'],
			parseTime: false,
			gridLineColor: 'transparent',
			lineColors: ['#1D69D6'],
			lineWidth: 2,
			pointSize: 4,
			smooth: false,
			hideHover: 'auto',
			resize: true,
		});
	}

	if (typeof window.Chart === 'undefined') {
		return;
	}

	var comparativoCanvas = document.getElementById('chart-comparativo');
	if (comparativoCanvas) {
		new Chart(comparativoCanvas, {
			type: 'bar',
			data: {
				labels: datos.comparativo_6_meses.map(function (p) { return p.etiqueta; }),
				datasets: [
					{
						label: 'Facturado',
						data: datos.comparativo_6_meses.map(function (p) { return p.facturado; }),
						backgroundColor: '#1D69D6',
					},
					{
						label: 'Cobrado',
						data: datos.comparativo_6_meses.map(function (p) { return p.cobrado; }),
						backgroundColor: '#1F2025',
					},
				],
			},
			options: {
				responsive: true,
				scales: { y: { beginAtZero: true } },
			},
		});
	}

	var distribucionCanvas = document.getElementById('chart-distribucion-estados');
	if (distribucionCanvas) {
		new Chart(distribucionCanvas, {
			type: 'polarArea',
			data: {
				labels: datos.distribucion_estados.map(function (p) { return p.estado; }),
				datasets: [{
					data: datos.distribucion_estados.map(function (p) { return p.cantidad; }),
					backgroundColor: ['#1D69D6', '#22C55E', '#F59E0B', '#EF4444', '#6B7280', '#8B5CF6'],
				}],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
			},
		});
	}
})();
