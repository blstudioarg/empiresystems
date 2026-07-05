# Quickstart / Validación: Logs de actividad de usuarios

Guía para validar la feature de extremo a extremo. Referencia:
[spec.md](./spec.md) · [plan.md](./plan.md) · [data-model.md](./data-model.md) ·
[contracts/http.md](./contracts/http.md).

## Prerrequisitos

```bash
php artisan migrate                    # aplica create_logs_actividad_table
php artisan test --filter=LogActividad # suite de la feature (debe quedar en verde)
```

## Escenarios de aceptación a verificar

Corresponden a los Acceptance Scenarios de la spec. Cada uno tiene su test automatizado; esta lista
es la checklist de validación manual/CI.

> **Estado**: validado por la suite automatizada (`php artisan test --filter=LogActividad`, 32
> tests en verde) — ver `tests/Feature/LogActividad*Test.php`. No se hizo verificación visual en
> navegador (decisión explícita del usuario al cerrar la feature); si se detecta algo raro en el
> dropdown o la datatable real, revisar primero `public/js/plugins-init/logs-datatable.init.js` y
> `resources/views/logs/index.blade.php`.

### US1 — Consultar el historial de actividad del tenant (P1)

- [x] El dropdown de usuario (header) muestra el ítem "Logs" junto a Profile/Configuración/Cerrar
      sesión, para cualquier rol (`usuario`, `admin`, `super_admin`).
- [x] Al hacer clic, carga `resources/views/logs/index.blade.php` con una datatable vacía que se
      llena vía AJAX (`GET /logs`), más reciente primero.
- [x] Crear/editar/eliminar un cliente, un artículo o una factura en borrador → aparece una fila
      nueva con usuario responsable, acción, entidad y fecha/hora.
- [x] Modificar cualquiera de las 4 pestañas de Configuración → aparece una fila de modificación.
- [x] Aprobar/rechazar un usuario → aparece una fila de alta/baja.
- [x] Iniciar sesión y cerrar sesión → aparecen ambos eventos en el log.

### US2 — Aislamiento multi-tenant del log (P1)

- [x] Con actividad generada en ≥2 tenants, un usuario del tenant A solo ve eventos del tenant A en
      `GET /logs`.
- [x] Variar `start`/`length`/`search[value]`/`order` en la petición JSON no expone filas del
      tenant B en ningún caso.

### US3 — Buscar y acotar el historial (P2)

- [x] Buscar por nombre de usuario, tipo de acción o nombre de entidad filtra correctamente sin
      recargar la página.
- [x] Ordenar por fecha o por usuario reordena las filas manteniendo la búsqueda aplicada.

### Edge cases

- [x] Tenant sin eventos todavía → tabla vacía con mensaje estándar, sin error.
- [x] Eliminar (físicamente) un usuario que generó eventos → sus filas conservan
      `usuario_nombre` en vez de romperse o desaparecer.
- [x] Varios eventos sobre el mismo registro (p. ej. una factura editada dos veces) → aparecen como
      filas independientes, no se agrupan ni se sobrescriben.
- [x] Super admin sin tenant (dominio central) inicia sesión → no genera fila ni rompe el login
      (edge case detectado durante la implementación, no estaba en la spec original: ver
      `LogActividadRegistroTest::test_login_de_super_admin_sin_tenant_no_genera_fila_ni_rompe_el_login`).

### Registro de accesos RGPD/LOPDGDD (enmienda post-MVP, ver Phase 7 de tasks.md)

- [x] Un login con contraseña incorrecta genera una fila `accion=login`, `resultado=fallo`, con la
      IP y el user-agent de la petición, sin `usuario_id` asociado.
- [x] Un login con un email que no corresponde a ningún usuario también genera una fila de intento
      fallido (`usuario_nombre` = email intentado) sin romper la respuesta de error normal del login.
- [x] Un intento de login fallido en el dominio central (sin tenant resuelto) no genera fila ni
      rompe el flujo (mismo criterio que el login exitoso de super admin).
- [x] Un login/alta/modificación exitosos quedan con `resultado=exito` e IP de origen capturada.
- [x] `php artisan logs:purgar` elimina las filas más antiguas que `logs.retencion_dias` (default
      730 días) de cada tenant, respetando la configuración por tenant y sin mezclar tenants; corre
      en lotes de 500 filas sin dejar residuos por encima del tamaño de lote.
- [x] El comando queda programado a diario en `bootstrap/app.php` (`php artisan schedule:list`
      debe mostrarlo).

## Comando de validación final

```bash
php artisan test --filter=LogActividad
```

Todos los archivos `LogActividadRegistroTest`, `LogActividadListadoTest`,
`LogActividadTenantIsolationTest`, `LogActividadUsuarioEliminadoTest` y `LogActividadPurgaTest`
deben pasar en verde, y no debe haber regresiones en el resto de la suite:

```bash
php artisan test
```
