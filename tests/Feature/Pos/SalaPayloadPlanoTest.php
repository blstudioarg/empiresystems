<?php

namespace Tests\Feature\Pos;

use App\Models\PosCuenta;
use App\Models\PosCuentaLinea;
use App\Models\PosMesa;
use App\Models\PosZona;
use App\Models\Tenant;
use App\Support\ConfigPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\GestionaRolesDeTenant;
use Tests\TestCase;

/**
 * Feature 041 — contrato del payload de la Sala del que depende la vista de plano en modo servicio
 * (specs/041-plano-sala-servicio/contracts/vista-sala.md §1).
 *
 * Este test no cubre lógica nueva: la feature no cambia el servidor (D8). Existe porque la vista de
 * plano añade un **segundo consumidor** del payload que usa diez campos, y hasta ahora solo lo
 * protegía el uso que hace la vista de tarjetas —que no mira la geometría—. Sin esto, un refactor
 * del controller dejaría el plano en blanco sin que ningún test se enterase.
 */
class SalaPayloadPlanoTest extends TestCase
{
    use GestionaRolesDeTenant, RefreshDatabase;

    /** Los diez campos que la vista de plano declara contrato. */
    private const CAMPOS_CONTRATO = [
        'fila', 'columna', 'ancho_celdas', 'alto_celdas', 'forma',
        'estado', 'olvidada', 'pendiente', 'abierta_hace_min', 'abrir_url',
    ];

    /** @return array{0: Tenant, 1: \App\Models\User} */
    private function tenantConSala(int $umbralOlvidadaMin = 90): array
    {
        $tenant = Tenant::factory()->create();
        ConfigPos::guardar($tenant->id, [
            'hosteleria_activo' => true,
            'mesa_olvidada_min' => $umbralOlvidadaMin,
        ]);
        $rol = $this->crearRol($tenant, 'Sala', ['ver-pos-sala', 'ver-pos-crear']);

        return [$tenant, $this->usuarioConRol($tenant, $rol)];
    }

    public function test_cada_mesa_llega_con_los_diez_campos_de_contrato_y_sus_tipos(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConSala();

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id]);
        PosMesa::factory()->create([
            'tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'nombre' => 'Mesa 4',
            'fila' => 1, 'columna' => 3, 'forma' => 'cuadrada',
            'ancho_celdas' => 2, 'alto_celdas' => 1,
        ]);

        $this->loginAs($user);
        $mesa = $this->getJson('/pos/sala')->assertOk()->json('mesas.0');

        foreach (self::CAMPOS_CONTRATO as $campo) {
            $this->assertArrayHasKey($campo, $mesa, "El payload de la Sala perdió el campo de contrato «{$campo}».");
        }

        $this->assertSame(1, $mesa['fila']);
        $this->assertSame(3, $mesa['columna']);
        $this->assertSame(2, $mesa['ancho_celdas']);
        $this->assertSame(1, $mesa['alto_celdas']);
        $this->assertSame('cuadrada', $mesa['forma']);
        $this->assertSame('libre', $mesa['estado']);
        $this->assertFalse($mesa['olvidada']);
        $this->assertIsString($mesa['pendiente']);
        $this->assertIsString($mesa['abrir_url']);
    }

    /**
     * Feature 042 — el lienzo de la zona es contrato de lectura.
     *
     * Sin este test, quitar los tres campos del controlador dejaría el plano dibujando 8×6 en toda
     * zona (el fallback del cliente) sin que nada fallara: la sala se vería "bien", solo que no
     * sería la sala del cliente.
     */
    public function test_cada_zona_llega_con_su_lienzo_medidas_y_celdas_recortadas(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConSala();

        PosZona::factory()->create([
            'tenant_id' => $tenant->id, 'nombre' => 'Terraza',
            'columnas' => 12, 'filas' => 8, 'celdas_inactivas' => ['0-10', '0-11'],
        ]);

        $this->loginAs($user);
        $zona = $this->getJson('/pos/sala')->assertOk()->json('zonas.0');

        foreach (['columnas', 'filas', 'celdas_inactivas'] as $campo) {
            $this->assertArrayHasKey($campo, $zona, "El payload de la Sala perdió el campo de contrato «{$campo}».");
        }

        $this->assertSame(12, $zona['columnas']);
        $this->assertSame(8, $zona['filas']);
        $this->assertSame(['0-10', '0-11'], $zona['celdas_inactivas']);
    }

    /**
     * Una zona anterior a la feature 042 (o recién creada) llega con el lienzo por defecto y la
     * máscara vacía, nunca con `null`: el cliente no tiene que defenderse de un campo ausente.
     */
    public function test_una_zona_sin_lienzo_propio_llega_con_el_lienzo_por_defecto(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConSala();

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id]);
        // Estado exacto de una zona migrada sin backfill: la columna JSON quedó a NULL.
        $zona->forceFill(['celdas_inactivas' => null])->saveQuietly();

        $this->loginAs($user);
        $zona = $this->getJson('/pos/sala')->assertOk()->json('zonas.0');

        $this->assertSame(8, $zona['columnas']);
        $this->assertSame(6, $zona['filas']);
        $this->assertSame([], $zona['celdas_inactivas']);
    }

    /**
     * FR-012: el lienzo viaja para cualquier usuario con acceso a la Sala. La vista de plano en
     * modo servicio la usa un camarero SIN permiso de configuración, y sin estos campos dibujaría
     * un contorno distinto del que colocó el encargado.
     */
    public function test_el_lienzo_viaja_tambien_sin_permiso_de_configuracion(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConSala();

        PosZona::factory()->create([
            'tenant_id' => $tenant->id, 'columnas' => 10, 'filas' => 9, 'celdas_inactivas' => ['1-1'],
        ]);

        // `tenantConSala()` NO concede `ver-configuracion`: es exactamente el camarero de FR-012.
        $this->loginAs($user);
        $zona = $this->getJson('/pos/sala')->assertOk()->json('zonas.0');

        $this->assertSame(10, $zona['columnas']);
        $this->assertSame(9, $zona['filas']);
        $this->assertSame(['1-1'], $zona['celdas_inactivas']);
    }

    public function test_una_mesa_sin_posicion_llega_con_fila_y_columna_a_null(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConSala();

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id]);
        PosMesa::factory()->create([
            'tenant_id' => $tenant->id, 'zona_id' => $zona->id,
            'fila' => null, 'columna' => null,
        ]);

        $this->loginAs($user);
        $mesa = $this->getJson('/pos/sala')->assertOk()->json('mesas.0');

        // `null` es lo que manda la mesa a la franja "sin sitio" de la vista de plano (FR-011).
        $this->assertNull($mesa['fila']);
        $this->assertNull($mesa['columna']);
    }

    public function test_olvidada_la_decide_el_servidor_con_el_umbral_del_tenant(): void
    {
        $this->sembrarPermisos();
        [$tenant, $user] = $this->tenantConSala(30);

        $zona = PosZona::factory()->create(['tenant_id' => $tenant->id]);
        $mesaVieja = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'nombre' => 'Mesa vieja', 'orden' => 1]);
        $mesaReciente = PosMesa::factory()->create(['tenant_id' => $tenant->id, 'zona_id' => $zona->id, 'nombre' => 'Mesa reciente', 'orden' => 2]);

        foreach ([[$mesaVieja, 45], [$mesaReciente, 5]] as [$mesa, $minutos]) {
            $cuenta = PosCuenta::factory()->create([
                'tenant_id' => $tenant->id, 'mesa_id' => $mesa->id,
                'abierta_en' => now()->subMinutes($minutos),
            ]);
            PosCuentaLinea::factory()->create([
                'tenant_id' => $tenant->id, 'cuenta_id' => $cuenta->id,
                'cantidad' => 1, 'precio_unitario' => 10, 'tipo_impositivo' => 10,
            ]);
        }

        $this->loginAs($user);
        $json = $this->getJson('/pos/sala')->assertOk()->json();

        $porNombre = collect($json['mesas'])->keyBy('nombre');

        $this->assertSame(30, $json['umbral_olvidada_min']);

        // La vista NUNCA hace aritmética de fechas (FR-008): recibe el veredicto ya tomado.
        $this->assertTrue($porNombre['Mesa vieja']['olvidada']);
        $this->assertSame('ocupada', $porNombre['Mesa vieja']['estado']);
        $this->assertGreaterThanOrEqual(30, $porNombre['Mesa vieja']['abierta_hace_min']);

        $this->assertFalse($porNombre['Mesa reciente']['olvidada']);
        $this->assertSame('ocupada', $porNombre['Mesa reciente']['estado']);

        // El importe por cobrar viaja como cadena formateada por el servidor: el plano lo pinta
        // tal cual, sin recalcularlo (Principio III).
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $porNombre['Mesa vieja']['pendiente']);
    }
}
