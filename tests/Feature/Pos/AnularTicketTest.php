<?php

namespace Tests\Feature\Pos;

use App\Models\Factura;
use App\Models\FacturaEvento;
use App\Models\LogActividad;
use App\Models\Pago;
use App\Models\PosCuenta;
use App\Models\Tenant;
use App\Models\Traduccion;
use App\Support\ConfigPos;
use App\Support\VerifactuTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ConCajaAbierta;
use Tests\Concerns\MontaSalaPos;
use Tests\TestCase;

/**
 * Feature 051 (TEST-FIRST): un ticket emitido no se borra, se **anula** desde el listado del POS.
 * Conserva su número (sin huecos), deja rastro (evento con motivo, registro de actividad y, con
 * Verifactu, registro de anulación), exige un permiso propio y respeta el aislamiento.
 */
class AnularTicketTest extends TestCase
{
    use ConCajaAbierta, MontaSalaPos, RefreshDatabase;

    private function montar(bool $conPermiso = true, array $atributosTenant = []): void
    {
        $this->montarSala(atributosTenant: $atributosTenant);

        if ($conPermiso) {
            $this->enTeam($this->tenantPos, fn () => $this->usuarioPos->roles->first()->givePermissionTo('anular-tickets'));
        }
    }

    private function emitir(float $precio = 2.50): Factura
    {
        $id = $this->postJson('/pos', [
            'lineas' => [['concepto' => 'Café', 'cantidad' => 1, 'precio_unitario' => $precio, 'tipo_impositivo' => 10]],
        ])->assertCreated()->json('id');

        return Factura::withoutGlobalScopes()->findOrFail($id);
    }

    private function listado(): array
    {
        return $this->getJson('/pos')->assertOk()->json();
    }

    public function test_anular_un_ticket_lo_deja_anulado_con_su_motivo_y_rastro(): void
    {
        $this->montar();
        $ticket = $this->emitir();

        $this->postJson("/pos/{$ticket->id}/anular", ['motivo' => 'Ticket duplicado'])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertSame('anulada', $ticket->fresh()->estado->value);
        $evento = FacturaEvento::withoutGlobalScopes()->where('factura_id', $ticket->id)->where('tipo_evento', 'anulada')->sole();
        $this->assertSame('Ticket duplicado', $evento->detalle['motivo']);
        $this->assertTrue(LogActividad::withoutGlobalScopes()->where('entidad_id', $ticket->id)->exists());
    }

    public function test_sin_motivo_no_se_anula(): void
    {
        $this->montar();
        $ticket = $this->emitir();

        $this->postJson("/pos/{$ticket->id}/anular", ['motivo' => ''])->assertStatus(422)->assertJsonValidationErrors('motivo');

        $this->assertSame('emitida', $ticket->fresh()->estado->value);
    }

    public function test_un_ticket_ya_anulado_no_se_vuelve_a_anular(): void
    {
        $this->montar();
        $ticket = $this->emitir();
        $this->postJson("/pos/{$ticket->id}/anular", ['motivo' => 'Error'])->assertOk();

        $this->postJson("/pos/{$ticket->id}/anular", ['motivo' => 'Otra vez'])->assertStatus(422);

        $this->assertSame(1, FacturaEvento::withoutGlobalScopes()->where('factura_id', $ticket->id)->where('tipo_evento', 'anulada')->count());
    }

    public function test_la_numeracion_no_se_reutiliza_ni_deja_huecos(): void
    {
        $this->montar();
        $primero = $this->emitir();
        $this->postJson("/pos/{$primero->id}/anular", ['motivo' => 'Error'])->assertOk();

        $segundo = $this->emitir();

        $this->assertSame($primero->numero + 1, $segundo->numero);
        $this->assertSame($primero->numero_completo, $primero->fresh()->numero_completo);
    }

    public function test_el_listado_marca_el_anulado_y_no_lo_suma(): void
    {
        $this->montar();
        $anulado = $this->emitir(10.00);
        $vigente = $this->emitir(2.00);
        $this->postJson("/pos/{$anulado->id}/anular", ['motivo' => 'Error'])->assertOk();

        $json = $this->listado();
        $filas = collect($json['data'])->keyBy('id');

        $this->assertTrue($filas[$anulado->id]['anulada']);
        $this->assertNull($filas[$anulado->id]['anular_url']);
        $this->assertFalse($filas[$vigente->id]['anulada']);
        $this->assertNotNull($filas[$vigente->id]['anular_url']);
        $this->assertSame(1, $json['totales']['total']);
        $this->assertSame(number_format((float) $vigente->total, 2, '.', ''), $json['totales']['importe_total']);
    }

    public function test_con_verifactu_activo_se_genera_el_registro_de_anulacion(): void
    {
        Queue::fake();
        $this->montar(atributosTenant: ['nif' => 'A58818501']);
        VerifactuTenant::activar($this->tenantPos->id, true);
        $ticket = $this->emitir();
        $this->assertNotEmpty($ticket->fresh()->huella);

        $this->postJson("/pos/{$ticket->id}/anular", ['motivo' => 'Error de cobro'])->assertOk();

        $this->assertTrue(FacturaEvento::withoutGlobalScopes()->where('factura_id', $ticket->id)->where('tipo_evento', 'verifactu_anulacion')->exists());
    }

    public function test_sin_el_permiso_no_se_ofrece_ni_se_permite(): void
    {
        $this->montar(conPermiso: false);
        $ticket = $this->emitir();

        $this->assertNull(collect($this->listado()['data'])->firstWhere('id', $ticket->id)['anular_url']);
        $this->postJson("/pos/{$ticket->id}/anular", ['motivo' => 'Error'])->assertForbidden();
        $this->assertSame('emitida', $ticket->fresh()->estado->value);
    }

    public function test_con_el_permiso_se_anula_sin_acceso_a_facturas(): void
    {
        $this->montar();
        $this->assertFalse($this->usuarioPos->fresh()->can('ver-facturas'));
        $ticket = $this->emitir();

        $this->postJson("/pos/{$ticket->id}/anular", ['motivo' => 'Error'])->assertOk();
    }

    public function test_un_ticket_de_otro_tenant_no_se_encuentra(): void
    {
        $otro = Tenant::factory()->create();
        $ajeno = Factura::factory()->emitida()->create(['tenant_id' => $otro->id, 'tipo' => 'simplificada']);
        $this->montar();

        $this->postJson("/pos/{$ajeno->id}/anular", ['motivo' => 'Error'])->assertNotFound();

        $this->assertSame('emitida', $ajeno->fresh()->estado->value);
    }

    public function test_un_ticket_con_un_cobro_registrado_aparte_no_se_anula(): void
    {
        $this->montar();
        $ticket = $this->emitir();
        Pago::factory()->create(['tenant_id' => $this->tenantPos->id, 'factura_id' => $ticket->id, 'importe' => 1.00]);

        $this->postJson("/pos/{$ticket->id}/anular", ['motivo' => 'Error'])->assertStatus(422);
        $this->assertNull(collect($this->listado()['data'])->firstWhere('id', $ticket->id)['anular_url']);
    }

    public function test_un_ticket_con_rectificativa_no_se_anula(): void
    {
        $this->montar();
        $ticket = $this->emitir();
        Factura::factory()->emitida()->create([
            'tenant_id' => $this->tenantPos->id,
            'tipo' => 'simplificada',
            'es_rectificativa' => true,
            'factura_rectificada_id' => $ticket->id,
        ]);

        $this->postJson("/pos/{$ticket->id}/anular", ['motivo' => 'Error'])->assertStatus(422);
        $this->assertSame('emitida', $ticket->fresh()->estado->value);
    }

    public function test_anular_el_ticket_de_una_cuenta_de_mesa_no_reabre_la_cuenta(): void
    {
        $this->montar();
        $cuenta = $this->abrirCuentaCon([['articulo' => $this->articuloPos(3.00, 10)]]);
        $id = $this->postJson("/pos/cuentas/{$cuenta['id']}/cobrar", ['version' => $cuenta['version']])->assertCreated()->json('factura.id')
            ?? Factura::withoutGlobalScopes()->latest('id')->value('id');

        $this->postJson("/pos/{$id}/anular", ['motivo' => 'Mesa equivocada'])->assertOk();

        $this->assertNotSame(PosCuenta::ESTADO_ABIERTA, PosCuenta::withoutGlobalScopes()->findOrFail($cuenta['id'])->estado);
    }

    public function test_con_el_pos_en_chino_el_mensaje_sale_traducido(): void
    {
        $this->montar();
        ConfigPos::guardar($this->tenantPos->id, ['idioma' => 'zh']);
        Traduccion::factory()->traducida('小票已作废。')->create(['texto' => 'Ticket anulado.']);
        $ticket = $this->emitir();

        $this->postJson("/pos/{$ticket->id}/anular", ['motivo' => '重复'])
            ->assertOk()
            ->assertJsonPath('message', '小票已作废。');

        // El motivo es un dato: se guarda tal cual.
        $this->assertSame('重复', FacturaEvento::withoutGlobalScopes()->where('factura_id', $ticket->id)->where('tipo_evento', 'anulada')->sole()->detalle['motivo']);
    }
}
