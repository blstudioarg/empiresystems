<?php

namespace Tests\Concerns;

use App\Models\Articulo;
use App\Models\PosMesa;
use App\Models\PosZona;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ConfigPos;

/**
 * Montaje común de los tests del módulo de hostelería (feature 038): un tenant con el módulo
 * activo, serie de simplificadas, permisos de sala y una zona con mesas.
 *
 * Existe para que cada test hable solo de lo suyo: sin esto, las diez líneas de andamiaje se
 * repetirían en cada archivo y taparían lo que realmente se está afirmando.
 */
trait MontaSalaPos
{
    use GestionaRolesDeTenant;

    protected Tenant $tenantPos;

    protected User $usuarioPos;

    protected PosZona $zonaPos;

    /** @var array<int, PosMesa> */
    protected array $mesasPos = [];

    /**
     * @param  array<string, mixed>  $capacidades  flags extra de ConfigPos
     * @param  array<string, mixed>  $atributosTenant  atributos extra del tenant (p. ej. `nif`
     *   real para tests de Verifactu). **Deben** ir aquí y no fijarse después de `loginAs()`:
     *   `stancl/tenancy` cachea la instancia del tenant al inicializar el contexto en el primer
     *   request, así que un cambio hecho a mitad de test (tras el login) queda invisible para
     *   `tenant()` en las peticiones siguientes.
     */
    protected function montarSala(array $capacidades = [], int $mesas = 2, array $atributosTenant = []): void
    {
        $this->sembrarPermisos();

        $this->tenantPos = Tenant::factory()->create($atributosTenant);
        Serie::factory()->simplificada()->for($this->tenantPos, 'tenant')->create();

        ConfigPos::guardar($this->tenantPos->id, array_merge([
            'hosteleria_activo' => true,
        ], $capacidades));

        $rol = $this->crearRol($this->tenantPos, 'Sala', [
            'ver-pos', 'ver-pos-crear', 'ver-pos-sala', 'ver-pos-opciones', 'ver-configuracion',
        ]);
        $this->usuarioPos = $this->usuarioConRol($this->tenantPos, $rol);

        $this->zonaPos = PosZona::factory()->create([
            'tenant_id' => $this->tenantPos->id,
            'nombre' => 'Comedor',
        ]);

        for ($i = 1; $i <= $mesas; $i++) {
            $this->mesasPos[$i] = PosMesa::factory()->create([
                'tenant_id' => $this->tenantPos->id,
                'zona_id' => $this->zonaPos->id,
                'nombre' => "Mesa {$i}",
            ]);
        }

        $this->loginAs($this->usuarioPos);
    }

    protected function articuloPos(float $precio = 10.0, float $tipo = 10.0, array $attrs = []): Articulo
    {
        return Articulo::factory()->create(array_merge([
            'tenant_id' => $this->tenantPos->id,
            'precio' => $precio,
            'tipo_impositivo' => $tipo,
        ], $attrs));
    }

    /**
     * Abre una cuenta en una mesa y le guarda las líneas indicadas, devolviendo el payload de la
     * cuenta tal como lo ve el cliente.
     *
     * @param  list<array{articulo: Articulo, cantidad?: float, opciones?: list<int>}>  $lineas
     * @return array<string, mixed>
     */
    protected function abrirCuentaCon(array $lineas, ?PosMesa $mesa = null): array
    {
        $mesa ??= $this->mesasPos[1];

        $cuenta = $this->postJson('/pos/cuentas', ['mesa_id' => $mesa->id])
            ->assertSuccessful()
            ->json();

        $payload = array_map(fn (array $linea) => [
            'articulo_id' => $linea['articulo']->id,
            'concepto' => $linea['articulo']->nombre,
            'cantidad' => $linea['cantidad'] ?? 1,
            'tipo_impositivo' => (float) $linea['articulo']->tipo_impositivo,
            'opciones' => array_map(fn (int $id) => ['opcion_id' => $id], $linea['opciones'] ?? []),
        ], $lineas);

        return $this->putJson("/pos/cuentas/{$cuenta['id']}", [
            'version' => $cuenta['version'],
            'mesa_id' => $mesa->id,
            'lineas' => $payload,
        ])->assertOk()->json();
    }
}
