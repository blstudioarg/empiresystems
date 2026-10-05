# Quickstart: validar la traducción del POS

## Prerrequisitos

- `.env` local con `DEEPL_API_KEY` y `DEEPL_API_URL` (ya están; ver `ACCESOS.local.md`).
- Migraciones nuevas aplicadas con `php artisan migrate` (solo crean `traducciones` y
  `traduccion_correcciones`; **no** usar `migrate:fresh`).
- Tenant "Empire Demo" con caja abierta y, para la Sala, el módulo de hostelería activo.

## Tests automáticos (sin llamar a DeepL: todo con `Http::fake()`)

```bash
php artisan test --filter=Traduccion
php artisan test tests/Feature/Pos
```

Esperado: todo en verde, incluidos los tests existentes del POS (que corren con el POS en español
y no deben ver ningún cambio).

## Traducción real

```bash
php artisan traducciones:sincronizar --solo-extraer   # cuántas claves encontró
php artisan traducciones:sincronizar                  # traduce las pendientes
```

Esperado: resumen con N nuevas / N traducidas / 0 error. Volver a ejecutarlo: 0 nuevas, 0 traducidas
(no gasta cupo).

## Validación manual (tablet o navegador en horizontal)

1. **Español intacto (SC-002)**: con el idioma en español, recorrer listado de tickets, Crear ticket,
   Sala, Caja, Opciones: todo igual que antes.
2. **Cambiar a chino (US1)**: Configuración → POS → Idioma del POS: 中文. Recorrer las mismas
   pantallas: títulos, botones, estados de mesa, modales, tablas (incluidos «Buscar»/«Anterior»),
   ayuda de cada pantalla y avisos (guardar cuenta, precuenta desactualizada, caja cerrada) en
   chino. Nombres de platos, mesas e importes sin cambios. Menú lateral: solo el grupo POS y el
   botón de ayuda en chino.
3. **Glosario (US2)**: «Precuenta» aparece como 预结单 (no 预算) en el chip, el modal y la ayuda.
4. **Fallo de la API (SC-005)**: poner una `DEEPL_API_KEY` inválida, añadir un texto nuevo y cargar
   la pantalla: el POS funciona, el texto nuevo sale en español; guardar, cobrar y cerrar caja OK.
5. **Corrección (US4)**: Configuración → POS → Traducciones del POS: corregir «Cobrar». Ver la
   corrección en el TPV. Ejecutar `traducciones:sincronizar`: la corrección sigue. Con otro tenant
   en chino: ve la traducción automática.
6. **Documentos (US5)**: crear un artículo con nombre chino, emitir ticket y precuenta: nombre
   legible, etiquetas en español y chino, mismo total, número y QR. Con el POS en español: igual
   que antes. Medir que el PDF no supera unos cientos de KB (subsetting).
7. **Volver a español**: todo vuelve a español al instante; las correcciones no se pierden.
