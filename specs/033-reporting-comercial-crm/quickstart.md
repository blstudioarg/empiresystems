# Quickstart — Validación de Reporting Comercial CRM (033)

Guía para comprobar de punta a punta que la feature funciona. Referencias de detalle en
[data-model.md](./data-model.md) y [contracts/informes-comerciales.md](./contracts/informes-comerciales.md).

## Prerrequisitos

- Features 027 (roles/permisos), 028 (leads/oportunidades/presupuestos) y 031 (Excel) operativas.
- Entorno local levantado y base de datos migrada.

```bash
php artisan migrate
php artisan db:seed --class=PermisosSeeder   # registra los dos permisos nuevos
```

## Comprobación automatizada

```bash
php artisan test --filter=InformeComercial
php artisan test --filter=CanalCaptacion
php artisan test --filter=Dashboard          # no debe romperse por la extracción de buckets
```

Todo en verde antes de dar por buena la feature. Las áreas críticas (aislamiento multi-tenant y
cálculo de ratios) van test-first, según el Principio IV.

## Recorrido manual

### 1. Catálogo de canales

1. Entrar en Configuración → Canales de captación.
2. Comprobar que aparece el catálogo sembrado por defecto.
3. Crear un canal nuevo; intentar crear otro con el mismo nombre → debe rechazarse.
4. Desactivar un canal que tenga leads → debe conservarse el histórico, no borrarse.

### 2. Canal en el alta de leads

1. Dar de alta un lead eligiendo un canal.
2. Dar de alta otro lead sin canal.
3. Importar un fichero de leads con una columna de canal, incluyendo una fila con un canal
   inexistente → esa fila entra sin canal y se reporta el motivo, sin abortar la importación.

### 3. Indicadores y ratios

1. Abrir Informes comerciales con el periodo por defecto.
2. Verificar contra un conteo manual: leads captados, convertidos, oportunidades por etapa,
   presupuestos por estado.
3. Comprobar que cada indicador muestra su criterio de fecha (cohorte / evento / instantánea).
4. Elegir un periodo sin actividad → todos los indicadores a cero y los ratios como "sin datos",
   nunca 0% ni error.

### 4. Segmentación

1. Filtrar por un canal → los indicadores se recalculan; la suma de todos los canales (incluido
   "Sin especificar") debe coincidir con el total sin filtrar.
2. Filtrar por un comercial, y luego combinar canal + comercial.
3. Cambiar el periodo con un filtro puesto → el filtro se mantiene.

### 5. Comparativa entre ejercicios

1. Asegurar actividad en el año en curso y en el anterior.
2. Activar la comparativa → cada indicador muestra actual, comparado y variación.
3. Elegir un periodo cuyo ejercicio anterior no tenga datos → variación "no calculable", sin
   división por cero.
4. Comprobar que las dos series del gráfico se alinean por posición en el periodo.

### 6. Alcance por perfil

1. Crear dos usuarios en el mismo tenant: uno con `ver-informes-comerciales` +
   `ver-informes-equipo`, otro solo con `ver-informes-comerciales`.
2. Asignar leads y oportunidades a cada uno.
3. Con el usuario restringido: solo ve su actividad y no dispone del filtro por comercial.
4. Forzar `comercial_id` de otro usuario en la URL → no debe devolver datos ajenos.
5. Quitar `ver-presupuestos` al usuario restringido → desaparece el bloque de presupuestos.
6. Usuario sin ninguno de los tres permisos de módulo → no accede a la sección.

### 7. Aislamiento multi-tenant

Con dos tenants con actividad, comprobar que ningún indicador, desglose o export de uno incluye
datos del otro. Es el criterio SC-005 y está cubierto por test automatizado.

### 8. Exportación

1. Aplicar filtros y exportar → el fichero refleja periodo, filtros y los mismos indicadores que la
   pantalla.
2. Exportar con el usuario restringido → el fichero solo contiene sus datos.
3. Exportar un periodo vacío → fichero válido con ceros.

### 9. Rendimiento

Con un tenant sembrado a volumen de SC-007 (≥5.000 leads, 1.000 oportunidades, 1.000 presupuestos en
el periodo), el informe debe presentarse en menos de 3 segundos.

## Antes de cerrar la feature

Las cuatro capas de documentación que exige CLAUDE.md:

1. `docs/06-kit-digital.md` — requisito 6 deja de ser gap parcial; `docs/03-modelo-datos.md` —
   `canales_captacion`, `leads.canal_captacion_id` y `leads.convertido_at`.
2. `docs/04-front-guidelines.md` — solo si surge una convención de UI reutilizable.
3. `resources/views/ayuda/` — guía de la nueva sección + actualizar la de leads (campo canal).
4. `resources/ia/conocimiento/` — nuevo `.md` del módulo de informes + actualizar el de leads.
