<?php

namespace Tests\Concerns;

use App\Models\CajaSesion;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Montaje común de los tests de la caja (feature 048): un tenant con serie de simplificadas, un
 * usuario con permisos de POS + caja ya logueado, y atajos para abrir, vender y cerrar por HTTP —
 * por el mismo camino que la tablet, no fabricando filas a mano.
 */
trait MontaCajaPos
{
    use GestionaRolesDeTenant;

    protected Tenant $tenantCaja;

    protected User $usuarioCaja;

    /** @param  array<string, mixed>  $atributosTenant */
    protected function montarCaja(array $atributosTenant = [], bool $login = true): void
    {
        $this->sembrarPermisos();
        $this->tenantCaja = Tenant::factory()->create($atributosTenant);
        Serie::factory()->simplificada()->for($this->tenantCaja, 'tenant')->create();

        $rol = $this->crearRol($this->tenantCaja, 'Caja', ['ver-pos', 'ver-pos-crear', 'ver-pos-caja', 'ver-configuracion']);
        $this->usuarioCaja = $this->usuarioConRol($this->tenantCaja, $rol);

        if ($login) {
            $this->loginAs($this->usuarioCaja);
        }
    }

    protected function abrirCajaHttp(string $fondo = '0'): CajaSesion
    {
        $id = $this->postJson('/pos/caja/abrir', ['fondo_inicial' => $fondo])->assertCreated()->json('sesion.id');

        return CajaSesion::withoutGlobalScopes()->findOrFail($id);
    }

    /**
     * Emite un ticket de una línea con `base` sin impuestos al tipo indicado.
     *
     * @param  list<array{metodo: string, importe: float|string}>|null  $pagos
     */
    protected function venderHttp(float $base, float $tipo = 21, ?array $pagos = null): int
    {
        $payload = ['lineas' => [['concepto' => 'Producto', 'cantidad' => 1, 'precio_unitario' => $base, 'tipo_impositivo' => $tipo]]];
        if ($pagos !== null) {
            $payload['pagos'] = $pagos;
        }

        return (int) $this->postJson('/pos', $payload)->assertCreated()->json('id');
    }

    /** Anula un ticket por debajo del flujo HTTP (el POS no expone anular tickets todavía). */
    protected function anularTicket(int $facturaId): void
    {
        DB::table('facturas')->where('id', $facturaId)->update(['estado' => 'anulada']);
    }

    /** @param  array<string, mixed>  $extra */
    protected function cerrarHttp(int $sesionId, array $extra): TestResponse
    {
        return $this->postJson('/pos/caja/cerrar', array_merge(['sesion_id' => $sesionId], $extra));
    }
}
