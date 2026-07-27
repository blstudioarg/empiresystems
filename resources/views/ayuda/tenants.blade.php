<p>Esta es la administración de <strong>tenants</strong> (las empresas cliente de la plataforma).
Es una pantalla de super administrador: cada tenant es un espacio aislado, con sus propios usuarios
y datos.</p>

<ol>
	<li><strong>Agregar tenant</strong>: das de alta una empresa nueva en la plataforma.</li>
	<li><strong>Activos</strong>: el indicador de arriba muestra cuántos tenants están operativos.</li>
	<li><strong>Gestionar</strong>: desde las acciones de cada fila administrás sus datos y estado.</li>
	<li><strong>Usuarios del tenant</strong>: al editar un tenant, más abajo del formulario ves el
	listado de todos sus usuarios (nombre, rol, email de acceso). Podés cambiarle el email a
	cualquiera de ellos y, si perdió su contraseña, establecerle una nueva directamente — no
	podés ver la contraseña actual (queda guardada de forma irreversible), solo reemplazarla.
	Dejar el campo de contraseña en blanco conserva la que ya tenía.</li>
</ol>

<p class="ayuda-nota">Los datos de un tenant nunca se cruzan con los de otro: todo queda aislado
por diseño. Es una operación sensible — un cambio acá afecta a toda una empresa cliente. Cambiar
el email o la contraseña de un usuario desde acá queda registrado en el log de actividad del
tenant, con quién lo hizo y cuándo.</p>
