# Clientes

Los clientes pueden ser **empresa** (con razón social y NIF/CIF obligatorios) o **particular**.
Se dan de alta desde la sección Clientes con nombre, datos fiscales, dirección y contacto. El NIF de
una empresa es único dentro del tenant. Desde aquí también se consultan sus facturas y presupuestos.

El listado se puede **exportar** a Excel y también **importar** desde un Excel/CSV propio (con
previsualización antes de confirmar) — ver `importacion-exportacion.md`.

## Perfil del cliente

Desde "Ver perfil" (menú Acciones del listado) se abre una página dedicada por cliente, en
pestañas: datos generales (nombre/razón social, NIF, dirección, contacto, recargo de
equivalencia, notas), resumen financiero (total facturado, pendiente de cobro, facturas vencidas
y ticket medio, calculado sobre sus facturas), facturas, presupuestos, albaranes y oportunidades
del cliente (cada listado paginado, con acceso al detalle), y una línea de tiempo que combina esos
cuatro tipos de evento ordenados del más reciente al más antiguo. Cada pestaña y cada acceso
rápido de creación (nueva factura/presupuesto/albarán/oportunidad, con el cliente ya
preseleccionado) respeta el permiso propio de ese módulo: si el usuario no tiene, por ejemplo,
`ver-facturas`, esa pestaña no aparece aunque sí pueda ver el cliente.
