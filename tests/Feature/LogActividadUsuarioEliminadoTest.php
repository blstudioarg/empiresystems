<?php

namespace Tests\Feature;

use App\Enums\AccionLogActividad;
use App\Models\LogActividad;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RegistradorActividad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogActividadUsuarioEliminadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_fila_conserva_el_nombre_del_usuario_tras_eliminarlo(): void
    {
        $tenant = Tenant::factory()->create();
        $usuario = User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ana Pérez']);

        $log = app(RegistradorActividad::class)->registrar(
            $usuario,
            AccionLogActividad::Login,
            null,
            null,
            'Inició sesión',
        );

        $usuario->delete();

        $log->refresh();

        $this->assertTrue(LogActividad::whereKey($log->id)->exists());
        $this->assertNull($log->usuario_id);
        $this->assertSame('Ana Pérez', $log->usuario_nombre);
    }
}
