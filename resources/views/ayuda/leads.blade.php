<p>Acá gestionás los <strong>leads</strong>: contactos potenciales que todavía no son clientes.
Se dan de alta manualmente o importando un fichero, se les hace seguimiento con notas, y cuando
están listos se convierten en cliente.</p>

<ol>
	<li><strong>Alta</strong>: el botón “Agregar lead” pide nombre y al menos un email o
		teléfono. Si ya existe un lead con ese email o teléfono en tu cuenta, se rechaza como
		duplicado.</li>
	<li><strong>Canal de captación</strong>: opcional, indica de dónde vino el lead (web, feria,
		recomendación...). Se administra desde el propio selector (agregar/renombrar/eliminar) y
		alimenta la segmentación de Informes comerciales; un lead sin canal se agrupa como "Sin
		especificar".</li>
	<li><strong>Importar</strong>: subí un CSV o Excel con columnas
		<code>nombre, empresa, email, telefono, canal</code> (canal es opcional). Las filas
		inválidas o duplicadas se reportan al final, sin bloquear la importación de las válidas;
		si el nombre de canal no existe en tu catálogo, la fila se importa igual, sin canal.</li>
	<li><strong>Exportar</strong>: el botón "Exportar" del listado descarga a Excel los leads que
		estás viendo (respeta el filtro activo).</li>
	<li><strong>Asignación</strong>: si dejás “Según regla de asignación vigente”, el lead se
		reparte automáticamente entre los comerciales configurados (o queda “Sin asignar” si no
		hay ninguno configurado).</li>
	<li><strong>Convertir en cliente</strong>: desde la ficha del lead, crea un cliente nuevo con
		sus datos. Es un paso final: un lead convertido no vuelve atrás.</li>
</ol>

<p class="ayuda-nota">Los leads descartados o sin convertir se purgan automáticamente pasado el
	plazo de retención configurado (protección de datos personales) — no hace falta borrarlos a
	mano.</p>
