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

## Fuera de alcance por ahora
- Diseño del frontend (template externo).
- Pasarela de cobro de las suscripciones del SaaS (se define más adelante).
