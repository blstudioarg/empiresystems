<?php

namespace Tests\Feature\Concerns;

use App\Models\Fichaje;
use App\Models\LogActividad;
use App\Models\MiembroEquipo;
use App\Models\Tenant;
use App\Models\User;

/**
 * Provisiona un segundo tenant con su propio usuario, LogActividad, MiembroEquipo y Fichaje de
 * prueba, para los tests de aislamiento multi-tenant de US4 y US5 (Principio I/IV).
 */
trait ConTenantSecundario
{
    protected Tenant $tenantSecundario;

    protected User $usuarioSecundario;

    protected LogActividad $logActividadSecundario;

    protected MiembroEquipo $miembroEquipoSecundario;

    protected Fichaje $fichajeSecundario;

    protected function crearTenantSecundario(): void
    {
        $this->tenantSecundario = Tenant::factory()->create();

        $this->usuarioSecundario = User::factory()->create([
            'tenant_id' => $this->tenantSecundario->id,
        ]);

        $this->logActividadSecundario = LogActividad::factory()->create([
            'tenant_id' => $this->tenantSecundario->id,
            'usuario_id' => $this->usuarioSecundario->id,
        ]);

        $this->miembroEquipoSecundario = MiembroEquipo::factory()->create([
            'tenant_id' => $this->tenantSecundario->id,
            'user_id' => $this->usuarioSecundario->id,
        ]);

        $this->fichajeSecundario = Fichaje::factory()->create([
            'tenant_id' => $this->tenantSecundario->id,
            'miembro_equipo_id' => $this->miembroEquipoSecundario->id,
        ]);
    }
}
