# Empire Systems — Visión del producto

## Qué es
SaaS de **facturación para España**, reconstrucción desde cero del CRM actual de Empire Systems con nueva tecnología.

## Stack
- **Backend:** Laravel 12
- **Frontend:** template de admin panel (lo aplica Federico, fuera de alcance por ahora)
- **Base de datos:** MySQL/MariaDB

## Modelo SaaS
- Multi-tenant: varias empresas (tenants) usan la misma aplicación.
- Un **Super Admin** da de alta nuevos clientes del SaaS desde el propio software.
- **Arquitectura de datos elegida:** base de datos **compartida** con columna `tenant_id` (ver `01-arquitectura.md` para la justificación).

## Alcance funcional inicial (MVP)
El corazón del producto es **facturar cumpliendo la normativa española vigente**:
- Gestión de clientes.
- Emisión de facturas (ordinaria, simplificada, rectificativa).
- Cálculo correcto de IVA, IRPF y recargo de equivalencia.
- Series y numeración correlativa sin huecos.
- Preparado para **Verifactu** (registro con huella/hash encadenado y QR) desde el diseño.
- Preparado para **factura electrónica B2B** (Ley Crea y Crece / RD 238/2026).

## Alcance ampliado — Control de stock (incorporado al MVP)
El producto incorpora **control de inventario con trazabilidad completa**, imprescindible
para gestionar el stock más allá de un contador suelto:
- Gestión de **proveedores** (de quién se compra el stock).
- **Compras / facturas de proveedor** que, al confirmarse, generan entradas de stock.
- **Kardex de movimientos de stock** (`movimientos_stock`) append-only: entradas (compras),
  salidas (emisión de facturas), ajustes manuales (inventario, roturas, mermas) y
  devoluciones, con trazabilidad y auditoría del stock resultante.
- Alertas de **stock mínimo** para reposición.

> Ver `docs/03-modelo-datos.md` (secciones `proveedores`, `compras`, `compra_lineas`,
> `movimientos_stock`) para el modelo de datos de esta fase.

## Alcance ampliado — Gestor documental (incorporado al MVP)
El producto incluye un **espacio de archivos por tenant** tipo "Drive": carpetas anidadas y
archivos ligeros (PDF, imágenes, ofimática, texto/csv), sin permisos por carpeta ni versionado —
todos los usuarios aprobados del tenant ven y gestionan todo el espacio. Subir/descargar/
renombrar/mover/borrar (cascada con confirmación reforzada) y previsualización inline de PDF e
imágenes. Almacenamiento en disco privado particionado por tenant, nunca accesible por URL
pública (ver Decisión 6 de `docs/01-arquitectura.md`).

> Ver `docs/03-modelo-datos.md` (secciones `carpetas`, `archivos`) para el modelo de datos de esta
> fase.

## Fuera de alcance por ahora
- Diseño del frontend (template externo).
- Pasarela de cobro de las suscripciones del SaaS (se define más adelante).
