<?php

namespace Tests\Unit;

use App\Ia\ConocimientoAsistente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConocimientoAsistenteTest extends TestCase
{
    use RefreshDatabase;

    private function activarTenant(Tenant $tenant): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getTenantKey());
    }

    public function test_ensamblado_modular_incluye_un_md_nuevo_sin_tocar_el_resto(): void
    {
        $dir = resource_path('ia/conocimiento');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $archivoTemporal = $dir.'/zzz-test-marcador.md';
        file_put_contents($archivoTemporal, "# Marcador de test\n\nContenido único MARCADOR-42.");

        try {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
            $this->activarTenant($tenant);

            $prompt = (new ConocimientoAsistente)->systemPrompt($user);

            $this->assertStringContainsString('MARCADOR-42', $prompt);
        } finally {
            @unlink($archivoTemporal);
        }
    }

    public function test_incluye_el_contexto_de_permisos_del_usuario(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->admin()->create(['tenant_id' => $tenant->id, 'name' => 'Ana Admin']);
        $this->activarTenant($tenant);

        $prompt = (new ConocimientoAsistente)->systemPrompt($user);

        $this->assertStringContainsString('Ana Admin', $prompt);
        $this->assertStringContainsString('Clientes', $prompt);
        $this->assertStringContainsString('Contexto del usuario', $prompt);
    }
}
