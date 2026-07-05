<?php

namespace App\Services;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Enums\ResultadoLogActividad;
use App\Models\LogActividad;
use App\Models\User;

class RegistradorActividad
{
    public function registrar(
        User $usuario,
        AccionLogActividad $accion,
        ?EntidadLogActividad $entidadTipo,
        ?int $entidadId,
        string $descripcion,
        ResultadoLogActividad $resultado = ResultadoLogActividad::Exito,
    ): LogActividad {
        return LogActividad::create([
            'tenant_id' => $usuario->tenant_id,
            'usuario_id' => $usuario->id,
            'usuario_nombre' => $usuario->name,
            'accion' => $accion,
            'resultado' => $resultado,
            'ip_origen' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'entidad_tipo' => $entidadTipo,
            'entidad_id' => $entidadId,
            'descripcion' => $descripcion,
            'ocurrido_at' => now(),
        ]);
    }

    /**
     * Registro de accesos (RGPD/LOPDGDD): intento de login fallido, sin usuario autenticado.
     * `tenantId` viene del tenant ya resuelto por dominio (SetTenantContext), no del usuario,
     * porque en un login fallido puede no existir un `User` correspondiente al email intentado.
     */
    public function registrarIntentoFallido(int $tenantId, string $emailIntentado, string $descripcion): LogActividad
    {
        return LogActividad::create([
            'tenant_id' => $tenantId,
            'usuario_id' => null,
            'usuario_nombre' => $emailIntentado,
            'accion' => AccionLogActividad::Login,
            'resultado' => ResultadoLogActividad::Fallo,
            'ip_origen' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'entidad_tipo' => null,
            'entidad_id' => null,
            'descripcion' => $descripcion,
            'ocurrido_at' => now(),
        ]);
    }
}
