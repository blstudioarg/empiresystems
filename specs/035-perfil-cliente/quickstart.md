# Quickstart: Perfil del cliente

Guía de validación manual end-to-end una vez implementada la feature. No sustituye a los tests
automatizados de `tasks.md`; sirve para confirmar visualmente que el flujo completo funciona.

## Prerrequisitos

- Entorno local levantado (`php artisan serve` o equivalente) con al menos un tenant con datos de
  demo: un cliente con varias facturas (algunas vencidas y sin cobrar), presupuestos en distintos
  estados, albaranes y oportunidades en distintas etapas del pipeline.
- Un usuario con permiso `ver-clientes` y todos los permisos de módulos relacionados
  (`ver-facturas`, `ver-presupuestos`, `ver-albaranes`, `ver-oportunidades` y sus variantes de
  creación) para validar el caso "todo visible".
- Un segundo usuario (o el mismo con rol reducido) sin alguno de esos permisos, para validar que
  las pestañas correspondientes se ocultan.
- Credenciales de desarrollo: ver `ACCESOS.local.md` (no reproducir aquí).

## Escenario 1 — Ver el perfil completo de un cliente con historial

1. Iniciar sesión con el usuario con todos los permisos.
2. Ir al listado de clientes (`/clientes`).
3. En la fila del cliente de demo con historial, abrir el dropdown "Acciones" → clic en
   "Ver perfil".
4. **Esperado**: se navega a `/clientes/{id}`, se abre la pestaña "Datos generales" por defecto,
   mostrando nombre/razón social, NIF, tipo, dirección completa, contacto, recargo de
   equivalencia y notas del cliente.
5. Cambiar a la pestaña "Resumen financiero". **Esperado**: total facturado, pendiente de cobro,
   facturas vencidas (cantidad e importe) y ticket medio, coherentes con las facturas reales del
   cliente (verificar cruzando manualmente contra `/facturas` filtrando ese cliente).
6. Cambiar a "Facturas", "Presupuestos", "Albaranes", "Oportunidades". **Esperado**: cada pestaña
   lista únicamente documentos de ese cliente, paginados, con enlace funcional a cada documento.
7. Cambiar a "Actividad". **Esperado**: línea de tiempo cronológica (más reciente primero) que
   mezcla eventos de los cuatro tipos, cada uno correctamente etiquetado.
8. Usar los accesos rápidos ("Nueva factura", "Nuevo presupuesto", "Nuevo albarán", "Nueva
   oportunidad"). **Esperado**: cada uno abre el formulario de alta correspondiente con el
   cliente ya preseleccionado.

## Escenario 2 — Cliente sin actividad

1. Crear (o usar) un cliente recién dado de alta, sin facturas/presupuestos/albaranes/
   oportunidades.
2. Abrir su perfil.
3. **Esperado**: pestaña financiera en cero sin error; cada pestaña de documentos muestra un
   estado vacío explicativo; la pestaña de actividad indica que aún no hay actividad registrada.

## Escenario 3 — Permisos por módulo

1. Iniciar sesión con el usuario sin permiso, por ejemplo, `ver-oportunidades`.
2. Abrir el perfil de un cliente con oportunidades registradas.
3. **Esperado**: la pestaña "Oportunidades" no aparece en la navegación de pestañas, y el acceso
   rápido "Nueva oportunidad" tampoco se muestra. El resto de pestañas para las que sí tiene
   permiso siguen visibles con normalidad.

## Escenario 4 — Aislamiento multi-tenant

1. Con dos tenants de prueba (Tenant A y Tenant B), anotar el `id` de un cliente del Tenant B.
2. Iniciar sesión como usuario del Tenant A.
3. Navegar directamente a `/clientes/{id-del-cliente-de-B}`.
4. **Esperado**: 404 (recurso no encontrado), sin exponer ningún dato del cliente del Tenant B.

## Escenario 5 — Sin permiso de clientes

1. Iniciar sesión con un usuario sin `ver-clientes`.
2. Intentar navegar directamente a `/clientes/{id}` de un cliente válido de su propio tenant.
3. **Esperado**: acceso denegado, igual que ya ocurre hoy al intentar acceder a `/clientes`.
