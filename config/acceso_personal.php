<?php

// Config del acceso personal de desarrollo (AccesoPersonalSeeder). Las credenciales reales viven
// en `.env` (no versionado) — ver `ACCESOS.local.md` en la raíz del repo (tampoco versionado).

return [
    'superadmin_email' => env('ACCESO_PERSONAL_SUPERADMIN_EMAIL', 'superadmin@empire.local'),
    'superadmin_password' => env('ACCESO_PERSONAL_SUPERADMIN_PASSWORD'),

    'tenant_nif' => env('ACCESO_PERSONAL_TENANT_NIF', 'B23456783'),
    'tenant_nombre' => env('ACCESO_PERSONAL_TENANT_NOMBRE', 'Empire Demo'),
    'tenant_domain' => env('ACCESO_PERSONAL_TENANT_DOMAIN', 'empire.localhost'),

    'tenant_admin_email' => env('ACCESO_PERSONAL_TENANT_ADMIN_EMAIL', 'admin@empire.local'),
    'tenant_admin_password' => env('ACCESO_PERSONAL_TENANT_ADMIN_PASSWORD'),
];
