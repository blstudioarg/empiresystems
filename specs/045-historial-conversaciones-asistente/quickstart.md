# Quickstart: validar el historial del asistente

**Feature**: 045-historial-conversaciones-asistente

Cómo comprobar que la feature funciona de punta a punta. Los detalles de esquema están en
[data-model.md](./data-model.md) y los de la API en [contracts/endpoints.md](./contracts/endpoints.md).

---

## Requisitos previos

- Entorno local levantado y accesible por el dominio de un tenant (ver `ACCESOS.local.md`).
- Clave de API del asistente configurada en Configuración → Asistente IA para ese tenant.
- Migraciones al día.

> **Aviso**: no ejecutar `migrate:fresh` para probar esto. Las migraciones de la feature son
> aditivas; un reset destruiría los datos de demo (regla explícita de `CLAUDE.md`).

```powershell
php artisan migrate
php artisan serve
```

---

## Escenario 1 — La conversación sobrevive a la sesión (User Story 1)

1. Abrir el asistente y mantener una conversación de tres o cuatro turnos, mencionando un dato
   concreto ("me llamo Federico", "trabajo con el cliente Textiles Sur").
2. Cerrar sesión y volver a entrar.
3. Abrir el asistente.

**Esperado**: aparece la misma conversación con todos sus mensajes. Al preguntar por el dato
mencionado antes, el asistente lo recuerda.

---

## Escenario 2 — Lista, retomar y conversación nueva (User Story 1)

1. Con la conversación anterior abierta, pulsar el icono de conversación nueva y escribir algo
   distinto.
2. Abrir el icono de historial.

**Esperado**: dos conversaciones, la más reciente primero, cada una titulada con su primer mensaje.
Al elegir la primera, el panel carga sus mensajes y el asistente responde con ese contexto, no con
el de la segunda.

---

## Escenario 3 — Aislamiento (SC-004, Principio I)

1. Anotar el id de una conversación propia (visible en la petición del historial).
2. Iniciar sesión como otra persona del mismo tenant y pedir `GET /asistente/conversaciones/{id}`
   con ese id.
3. Repetir desde un usuario de otro tenant.

**Esperado**: `404` en ambos casos, no `403` ni contenido. El historial de cada persona muestra solo
lo suyo.

Esta comprobación está cubierta por tests automatizados (Principio IV: se escriben antes que la
implementación); el paso manual solo confirma el comportamiento en la UI real.

---

## Escenario 4 — Compactación (User Story 2)

1. Llevar una conversación por encima de 30 mensajes. Para no hacerlo a mano, sembrar mensajes
   con un tinker corto sobre la conversación activa y luego enviar un mensaje desde el panel.
2. Observar el indicador de progreso al enviar.

**Esperado**: el indicador muestra que se está compactando antes de responder. Tras la respuesta, la
conversación muestra el separador de la parte resumida. El asistente sigue respondiendo
correctamente sobre algo mencionado al principio del hilo.

**Verificar el fallback (FR-013)**: con una clave de API inválida solo para la llamada de resumen
—o forzando el fallo en el servicio de compactación— el turno debe responder igualmente, recurriendo
al recorte simple, sin mensaje de error para la persona.

---

## Escenario 5 — Borrado (User Story 3)

1. En el historial, borrar una conversación. Debe pedir confirmación.
2. Confirmar.

**Esperado**: desaparece de la lista con un toast de éxito. Si era la abierta, el panel queda en un
hilo nuevo y vacío. Sus mensajes ya no existen en base de datos.

---

## Escenario 6 — Retención y purga (FR-016 a FR-019)

```powershell
# Ver el efecto sin borrar nada real: envejecer una conversación de prueba
php artisan tinker
# > DB::table('asistente_conversaciones')->where('id', <id>)->update(['ultima_actividad_en' => now()->subDays(120)]);

php artisan asistente:purgar
```

**Esperado**: el comando informa cuántas conversaciones purgó por tenant y con qué plazo. La
conversación envejecida desaparece junto con sus mensajes; las recientes no se tocan.

Comprobar también que el plazo es configurable: cambiar `asistente.retencion_dias` del tenant a
`1` y verificar que el comando ahora alcanza conversaciones de ayer.

---

---

## Escenario 7 — Rendimiento del historial (SC-002)

Sembrar 50 conversaciones para el usuario de prueba y medir lo que tarda abrir el historial y
retomar una de ellas.

```powershell
php artisan tinker
# > App\Models\AsistenteConversacion::factory()->count(50)->create(['tenant_id' => <t>, 'user_id' => <u>]);
```

**Esperado**: la lista aparece y una conversación se retoma en **menos de 3 segundos** en total.
Anotar el tiempo medido; si se pasa, revisar los índices de `data-model.md` antes de dar la tarea
por buena.

---

## Escenario 8 — Coste del turno que compacta (SC-007)

1. Cronometrar un turno normal (conversación corta, misma pregunta).
2. Cronometrar el turno que cruza el umbral y dispara la compactación.

**Esperado**: el turno que compacta tarda **como mucho el doble** que el normal. Anotar ambos
números.

---

## Guion de comprobación de la compactación (SC-003)

Antes de compactar, mencionar en la conversación **10 datos concretos** (nombres de cliente,
importes, referencias de factura). Tras la compactación, preguntar por los 10, uno a uno.

**Esperado**: el asistente responde correctamente sobre **al menos 9 de los 10**. Menos que eso
significa que el resumen está perdiendo información y hay que revisar el prompt de
`CompactadorConversacion`.

---

## Tests automatizados

```powershell
php artisan test tests/Feature/Asistente
php artisan test tests/Unit/CompactadorConversacionTest.php
```

Deben quedar en verde, incluidos los tests existentes del asistente: esta feature cambia dónde vive
la conversación, pero no el comportamiento del flujo de confirmación de escrituras (FR-022).
