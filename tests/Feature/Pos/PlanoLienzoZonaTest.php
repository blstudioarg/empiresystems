<?php

namespace Tests\Feature\Pos;

use App\Models\PosMesa;
use App\Models\PosZona;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * Feature 042 — lienzo por zona en el guardado del plano
 * (`PUT /pos/sala/zonas/{zona}/plano`, specs/042-.../contracts/plano-zona.md §2).
 *
 * Cubre los invariantes de geometría G4 (mesa sobre celda recortada) y G5 (medidas en rango y al
 * menos una celda de sala), la normalización de `celdas_inactivas` y la regla de que **todo se
 * valida contra la geometría PROPUESTA en el payload, no contra la persistida** (D8): reducir la
 * zona y mover la mesa que estorbaba en la misma petición es una operación legítima.
 *
 * Aquí es donde un bug destruye el plano de un cliente, así que la barrera es de servidor: el
 * bloqueo en cliente es previsualización, no seguridad.
 */
class PlanoLienzoZonaTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    /** @return array{0: Tenant, 1: User} */
    private function tenantConConfiguracion(): array
    {
        $tenant = Tenant::factory()->create();
        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true]);
        $rol = $this->crearRol($tenant, 'Configuración', ['ver-configuracion', 'ver-pos-sala']);

        return [$tenant, $this->usuarioConRol($tenant, $rol)];
    }

    /**
     * @param  array<int, string>  $celdasInactivas
     * @param  array<int, array<string, mixed>>  $mesas
     * @return array<string, mixed>
     */
    private function cuerpo(int $columnas, int $filas, array $celdasInactivas, array $mesas, int $version = 1): array
    {
        return [
            'version' => $version,
            'columnas' => $columnas,
            'filas' => $filas,
            'celdas_inactivas' => $celdasInactivas,
            'mesas' => $mesas,
        ];
    }

    /** @return array<string, mixed> */
    private function mesa(PosMesa $mesa, int $fila, int $columna, int $ancho = 1, int $alto = 1): array
    {
        return [
            'id' => $mesa->id, 'fila' => $fila, 'columna' => $columna,
            'ancho_celdas' => $ancho, 'alto_celdas' => $alto, 'forma' => 'cuadrada',
        ];
    }

    // ── Controles del lienzo en la vista (FR-018, T030) ─────────────────────────────────────

    /**
     * El lienzo lo VE todo el mundo (viaja en el payload para la vista de servicio), pero
     * cambiarlo es del encargado: sin `ver-configuracion` no se renderiza ni el control de medidas
     * ni el de recorte.
     *
     * Se comprueba sobre marcadores de HTML (los `id` de los controles) y no sobre texto visible:
     * la guía in-app de la pantalla se renderiza siempre y menciona esas mismas acciones, así que
     * un `assertSee` de texto pasaría o fallaría por el motivo equivocado.
     */
    public function test_sin_permiso_de_configuracion_no_se_renderizan_los_controles_del_lienzo(): void
    {
        $this->sembrarPermisos();

        $tenant = Tenant::factory()->create();
        ConfigPos::guardar($tenant->id, ['hosteleria_activo' => true]);
        $rol = $this->crearRol($tenant, 'Camarero', ['ver-pos-sala', 'ver-pos-crear']);
        $camarero = $this->usuarioConRol($tenant, $rol);

        PosZona::factory()->create(['tenant_id' => $tenant->id]);

        $this->loginAs($camarero);
        $html = $this->get('/pos/sala')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="pos-plano-columnas"', $html);
        $this->assertStringNotContainsString('id="pos-plano-filas"', $html);
        $this->assertStringNotContainsString('id="pos-plano-recorte"', $html);

        // Tampoco el menu de acciones de la mesa: renombrar exige `ver-configuracion`
        // (`can:ver-configuracion` sobre la ruta), asi que sin permiso tocar una mesa tiene que
        // seguir yendo derecho a su ticket, sin un paso intermedio que ademas no podria usar.
        $this->assertStringNotContainsString('id="pos-plano-mesa-menu"', $html);

        // Pero el módulo de dibujo se sigue cargando fuera del guard del editor: es lo que la
        // feature 041 vino a arreglar y esta feature no puede volver a romper.
        $this->assertStringContainsString('pos-plano-dibujo.js', $html);
    }

    public function test_con_permiso_de_configuracion_los_controles_del_lienzo_estan_presentes(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion();

        PosZona::factory()->create(['tenant_id' => $tenant->id]);

        $this->loginAs($user);
        $html = $this->get('/pos/sala')->assertOk()->getContent();

        $this->assertStringContainsString('id="pos-plano-columnas"', $html);
        $this->assertStringContainsString('id="pos-plano-filas"', $html);
        $this->assertStringContainsString('id="pos-plano-recorte"', $html);
        $this->assertStringContainsString('id="pos-plano-mesa-menu"', $html);
    }

    // ── Medidas y G5 ────────────────────────────────────────────────────────────────────────

    public function test_guarda_medidas_nuevas_y_devuelve_la_version_incrementada(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion();

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 1]);
        $mesa = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 0]);

        $this->loginAs($user);

        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", $this->cuerpo(14, 10, [], [
            $this->mesa($mesa, 9, 13),
        ]))->assertOk()->assertJson(['version' => 2]);

        $this->assertDatabaseHas('pos_zonas', ['id' => $zona->id, 'columnas' => 14, 'filas' => 10, 'version' => 2]);
        $this->assertDatabaseHas('pos_mesas', ['id' => $mesa->id, 'fila' => 9, 'columna' => 13]);
    }

    /** @return array<string, array{0: int, 1: int}> */
    public static function medidasFueraDeRango(): array
    {
        return [
            'columnas por debajo del mínimo' => [3, 6],
            'filas por debajo del mínimo' => [8, 3],
            'columnas por encima del máximo' => [25, 6],
            'filas por encima del máximo' => [8, 25],
        ];
    }

    #[DataProvider('medidasFueraDeRango')]
    public function test_medidas_fuera_del_rango_permitido_se_rechazan(int $columnas, int $filas): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion();

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 1]);
        $mesa = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 0]);

        $this->loginAs($user);

        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", $this->cuerpo($columnas, $filas, [], [
            $this->mesa($mesa, 0, 0),
        ]))->assertStatus(422);

        $this->assertDatabaseHas('pos_zonas', ['id' => $zona->id, 'columnas' => 8, 'filas' => 6, 'version' => 1]);
    }

    public function test_recortar_la_zona_entera_se_rechaza_por_g5(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion();

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 1]);

        // 4×4 recortada por completo: ni una celda de sala.
        $todas = [];
        for ($f = 0; $f < 4; $f++) {
            for ($c = 0; $c < 4; $c++) {
                $todas[] = "{$f}-{$c}";
            }
        }

        $this->loginAs($user);

        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", $this->cuerpo(4, 4, $todas, []))
            ->assertStatus(422);

        $this->assertDatabaseHas('pos_zonas', ['id' => $zona->id, 'columnas' => 8, 'filas' => 6, 'version' => 1]);
        $this->assertSame([], $zona->fresh()->celdasInactivas());
    }

    // ── Bloqueo optimista ───────────────────────────────────────────────────────────────────

    public function test_una_version_desactualizada_devuelve_409_sin_tocar_el_lienzo(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion();

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 4]);
        $mesa = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 0, 'columna' => 0]);

        $this->loginAs($user);

        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", $this->cuerpo(12, 10, ['0-0'], [
            $this->mesa($mesa, 1, 1),
        ], version: 3))->assertStatus(409);

        $this->assertDatabaseHas('pos_zonas', ['id' => $zona->id, 'columnas' => 8, 'filas' => 6, 'version' => 4]);
        $this->assertDatabaseHas('pos_mesas', ['id' => $mesa->id, 'fila' => 0, 'columna' => 0]);
    }

    // ── G4: mesa sobre celda recortada (T016) ───────────────────────────────────────────────

    public function test_una_mesa_sobre_una_celda_recortada_se_rechaza_por_g4(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion();

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 1]);
        $mesa = PosMesa::factory()->create([
            'tenant_id' => $tenant->id, 'zona_id' => $zona->id,
            'nombre' => 'Mesa 7', 'fila' => 2, 'columna' => 2,
        ]);

        $this->loginAs($user);

        // La mesa ocupa 2×2 desde (2,2); "3-3" cae dentro de su rectángulo.
        $respuesta = $this->putJson("/pos/sala/zonas/{$zona->id}/plano", $this->cuerpo(8, 6, ['3-3'], [
            $this->mesa($mesa, 2, 2, ancho: 2, alto: 2),
        ]));

        $respuesta->assertStatus(422);
        $this->assertStringContainsString('Mesa 7', json_encode($respuesta->json()));

        $this->assertDatabaseHas('pos_zonas', ['id' => $zona->id, 'version' => 1]);
        $this->assertSame([], $zona->fresh()->celdasInactivas());
        $this->assertDatabaseHas('pos_mesas', ['id' => $mesa->id, 'ancho_celdas' => 1, 'alto_celdas' => 1]);
    }

    // ── Normalización de la máscara (T016) ──────────────────────────────────────────────────

    public function test_las_celdas_fuera_de_la_rejilla_propuesta_se_descartan(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion();

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 1]);

        $this->loginAs($user);

        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", $this->cuerpo(6, 5, [
            '0-0', '4-5', '9-9', '0-6', '5-0',
        ], []))->assertOk();

        // Solo sobreviven las que caen dentro de 6 columnas × 5 filas. Las demás se descartan (no
        // se recuerdan): si más tarde la zona se agranda, vuelven como suelo.
        $this->assertSame(['0-0', '4-5'], $zona->fresh()->celdasInactivas());
    }

    public function test_las_celdas_duplicadas_y_desordenadas_se_persisten_normalizadas(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion();

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id, 'version' => 1]);

        $this->loginAs($user);

        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", $this->cuerpo(8, 6, [
            '2-3', '0-1', '2-3', '0-0', '1-5', '0-1',
        ], []))->assertOk();

        $this->assertSame(['0-0', '0-1', '1-5', '2-3'], $zona->fresh()->celdasInactivas());
    }

    // ── Reducción de medidas: la geometría propuesta manda (T024, D8) ───────────────────────

    public function test_reducir_las_medidas_dejando_una_mesa_fuera_se_rechaza_nombrando_la_mesa(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion();

        $zona = PosZona::factory()->create([
            'tenant_id' => $tenant->id, 'version' => 1, 'columnas' => 8, 'filas' => 6,
        ]);
        $mesa = PosMesa::factory()->create([
            'tenant_id' => $tenant->id, 'zona_id' => $zona->id,
            'nombre' => 'Mesa del rincon', 'fila' => 5, 'columna' => 7,
        ]);

        $this->loginAs($user);

        $respuesta = $this->putJson("/pos/sala/zonas/{$zona->id}/plano", $this->cuerpo(5, 4, [], [
            $this->mesa($mesa, 5, 7),
        ]));

        $respuesta->assertStatus(422);
        $this->assertStringContainsString('Mesa del rincon', json_encode($respuesta->json()));

        $this->assertDatabaseHas('pos_zonas', ['id' => $zona->id, 'columnas' => 8, 'filas' => 6, 'version' => 1]);
        $this->assertDatabaseHas('pos_mesas', ['id' => $mesa->id, 'fila' => 5, 'columna' => 7]);
    }

    public function test_reducir_las_medidas_y_mover_la_mesa_en_la_misma_peticion_es_valido(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConConfiguracion();

        $zona = PosZona::factory()->create([
            'tenant_id' => $tenant->id, 'version' => 1, 'columnas' => 8, 'filas' => 6,
        ]);
        $mesa = PosMesa::factory()->create([
            'tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'fila' => 5, 'columna' => 7,
        ]);

        $this->loginAs($user);

        // Se valida la geometría PROPUESTA (D8): la mesa ya viaja dentro de la rejilla nueva.
        $this->putJson("/pos/sala/zonas/{$zona->id}/plano", $this->cuerpo(5, 4, [], [
            $this->mesa($mesa, 0, 0),
        ]))->assertOk();

        $this->assertDatabaseHas('pos_zonas', ['id' => $zona->id, 'columnas' => 5, 'filas' => 4, 'version' => 2]);
        $this->assertDatabaseHas('pos_mesas', ['id' => $mesa->id, 'fila' => 0, 'columna' => 0]);
    }
}
