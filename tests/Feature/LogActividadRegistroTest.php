<?php

namespace Tests\Feature;

use App\Enums\AccionLogActividad;
use App\Enums\EntidadLogActividad;
use App\Enums\ResultadoLogActividad;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\LogActividad;
use App\Models\Serie;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogActividadRegistroTest extends TestCase
{
    use RefreshDatabase;

    private function fila(AccionLogActividad $accion, ?EntidadLogActividad $entidadTipo = null): ?LogActividad
    {
        return LogActividad::where('accion', $accion)
            ->when($entidadTipo, fn ($query) => $query->where('entidad_tipo', $entidadTipo))
            ->latest('id')
            ->first();
    }

    public function test_login_registra_evento(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $this->loginAs($user);

        $log = $this->fila(AccionLogActividad::Login);
        $this->assertNotNull($log);
        $this->assertSame($user->name, $log->usuario_nombre);
        $this->assertSame($tenant->id, $log->tenant_id);
        $this->assertSame(ResultadoLogActividad::Exito, $log->resultado);
        $this->assertNotNull($log->ip_origen);
    }

    public function test_login_fallido_por_password_incorrecta_registra_intento_denegado(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);

        $this->actingOnDomain($this->domainFor($tenant));
        $this->post('/login', ['email' => $user->email, 'password' => 'contraseña-incorrecta']);

        $log = LogActividad::where('accion', AccionLogActividad::Login)
            ->where('resultado', ResultadoLogActividad::Fallo)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertNull($log->usuario_id);
        $this->assertSame($user->email, $log->usuario_nombre);
        $this->assertSame($tenant->id, $log->tenant_id);
        $this->assertGuest();
    }

    public function test_login_fallido_con_email_inexistente_no_rompe_y_registra_el_intento(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingOnDomain($this->domainFor($tenant));
        $response = $this->post('/login', ['email' => 'no-existe@example.com', 'password' => 'lo-que-sea']);

        $response->assertSessionHasErrors('email');

        $log = LogActividad::where('accion', AccionLogActividad::Login)
            ->where('resultado', ResultadoLogActividad::Fallo)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertNull($log->usuario_id);
        $this->assertSame('no-existe@example.com', $log->usuario_nombre);
    }

    public function test_login_fallido_en_dominio_central_no_rompe_por_falta_de_tenant(): void
    {
        $response = $this->post('http://localhost/login', ['email' => 'quien-sea@example.com', 'password' => 'x']);

        $response->assertSessionHasErrors('email');
        $this->assertSame(0, LogActividad::count());
    }

    public function test_login_de_super_admin_sin_tenant_no_genera_fila_ni_rompe_el_login(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['password' => bcrypt('secret123')]);

        $response = $this->loginAs($superAdmin);

        $response->assertRedirect('/');
        $this->assertNull($this->fila(AccionLogActividad::Login));
    }

    public function test_logout_registra_evento(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->post('/logout');

        $log = $this->fila(AccionLogActividad::Logout);
        $this->assertNotNull($log);
        $this->assertSame($user->name, $log->usuario_nombre);
    }

    public function test_cliente_alta_modificacion_baja_registran_eventos(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->post('/clientes', ['tipo' => 'particular', 'nombre' => 'Cliente Log', 'pais' => 'ES']);
        $cliente = Cliente::firstWhere('nombre', 'Cliente Log');
        $this->assertNotNull($this->fila(AccionLogActividad::Alta, EntidadLogActividad::Cliente));

        $this->put("/clientes/{$cliente->id}", ['tipo' => 'particular', 'nombre' => 'Cliente Log Editado', 'pais' => 'ES']);
        $this->assertNotNull($this->fila(AccionLogActividad::Modificacion, EntidadLogActividad::Cliente));

        $this->delete("/clientes/{$cliente->id}");
        $this->assertNotNull($this->fila(AccionLogActividad::Baja, EntidadLogActividad::Cliente));
    }

    public function test_articulo_alta_modificacion_baja_registran_eventos(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->post('/articulos', ['tipo' => 'producto', 'nombre' => 'Articulo Log', 'precio' => 10, 'tipo_impositivo' => 21]);
        $articulo = \App\Models\Articulo::firstWhere('nombre', 'Articulo Log');
        $this->assertNotNull($this->fila(AccionLogActividad::Alta, EntidadLogActividad::Articulo));

        $this->put("/articulos/{$articulo->id}", ['tipo' => 'producto', 'nombre' => 'Articulo Log Editado', 'precio' => 12, 'tipo_impositivo' => 21]);
        $this->assertNotNull($this->fila(AccionLogActividad::Modificacion, EntidadLogActividad::Articulo));

        $this->delete("/articulos/{$articulo->id}");
        $this->assertNotNull($this->fila(AccionLogActividad::Baja, EntidadLogActividad::Articulo));
    }

    public function test_configuracion_apariencia_registra_evento(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->put('/configuracion/apariencia', [
            'color_primario' => '#112233',
            'color_secundario' => '#445566',
            'color_topbar' => '#778899',
        ]);

        $this->assertNotNull($this->fila(AccionLogActividad::Modificacion, EntidadLogActividad::Configuracion));
    }

    public function test_configuracion_facturacion_registra_evento(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->put('/configuracion/facturacion', ['simplificada_tope_ampliado' => true]);

        $this->assertNotNull($this->fila(AccionLogActividad::Modificacion, EntidadLogActividad::Configuracion));
    }

    public function test_configuracion_email_registra_evento(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->put('/configuracion/email', [
            'smtp_host' => 'smtp.hostinger.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_usuario' => 'facturas@empresa.test',
            'smtp_password' => 'secreto-smtp',
            'remitente' => 'facturas@empresa.test',
            'remitente_nombre' => 'Empresa Demo',
            'responder_a' => 'soporte@empresa.test',
        ]);

        $this->assertNotNull($this->fila(AccionLogActividad::Modificacion, EntidadLogActividad::Configuracion));
    }

    public function test_configuracion_archivos_registra_evento(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $this->loginAs($user);

        $this->put('/configuracion/archivos', ['limite_mb' => 50]);

        $this->assertNotNull($this->fila(AccionLogActividad::Modificacion, EntidadLogActividad::Configuracion));
    }

    public function test_registro_usuario_pendiente_registra_evento(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingOnDomain($this->domainFor($tenant));

        $this->post('/registro', [
            'name' => 'Solicitante Log',
            'email' => 'solicitante.log@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $log = $this->fila(AccionLogActividad::Alta, EntidadLogActividad::Usuario);
        $this->assertNotNull($log);
        $this->assertSame('Solicitante Log', $log->usuario_nombre);
    }

    public function test_aprobar_usuario_registra_evento(): void
    {
        $tenant = Tenant::factory()->create();
        $aprobador = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $pendiente = User::factory()->pendiente()->create(['tenant_id' => $tenant->id]);
        $this->loginAs($aprobador);

        $this->patch("/usuarios/{$pendiente->id}/aprobar");

        $log = $this->fila(AccionLogActividad::Alta, EntidadLogActividad::Usuario);
        $this->assertNotNull($log);
        $this->assertSame($aprobador->name, $log->usuario_nombre);
    }

    public function test_rechazar_usuario_registra_evento(): void
    {
        $tenant = Tenant::factory()->create();
        $aprobador = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $pendiente = User::factory()->pendiente()->create(['tenant_id' => $tenant->id]);
        $this->loginAs($aprobador);

        $this->patch("/usuarios/{$pendiente->id}/rechazar");

        $log = $this->fila(AccionLogActividad::Baja, EntidadLogActividad::Usuario);
        $this->assertNotNull($log);
        $this->assertSame($aprobador->name, $log->usuario_nombre);
    }

    private function facturaBorradorValida(Tenant $tenant): array
    {
        $serie = Serie::factory()->create(['tenant_id' => $tenant->id]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        return [$serie, $cliente];
    }

    public function test_factura_alta_modificacion_baja_registran_eventos(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        [, $cliente] = $this->facturaBorradorValida($tenant);
        $this->loginAs($user);

        $this->post('/facturas', [
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'lineas' => [
                ['concepto' => 'Consultoría', 'cantidad' => 1, 'precio_unitario' => 100, 'tipo_impositivo' => 21],
            ],
        ]);
        $factura = Factura::first();
        $this->assertNotNull($this->fila(AccionLogActividad::Alta, EntidadLogActividad::Factura));

        $this->put("/facturas/{$factura->id}", [
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'lineas' => [
                ['concepto' => 'Consultoría', 'cantidad' => 2, 'precio_unitario' => 100, 'tipo_impositivo' => 21],
            ],
        ]);
        $this->assertNotNull($this->fila(AccionLogActividad::Modificacion, EntidadLogActividad::Factura));

        $this->delete("/facturas/{$factura->id}");
        $this->assertNotNull($this->fila(AccionLogActividad::Baja, EntidadLogActividad::Factura));
    }

    public function test_factura_emision_registra_evento(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        [$serie, $cliente] = $this->facturaBorradorValida($tenant);
        $cliente->update(['nif' => '12345678Z', 'direccion' => 'Calle Falsa 123']);
        $this->loginAs($user);

        $this->post('/facturas', [
            'cliente_id' => $cliente->id,
            'fecha_expedicion' => now()->toDateString(),
            'forma_pago' => 'transferencia',
            'lineas' => [
                ['concepto' => 'Consultoría', 'cantidad' => 1, 'precio_unitario' => 100, 'tipo_impositivo' => 21],
            ],
        ]);
        $factura = Factura::first();

        $this->post("/facturas/{$factura->id}/emitir");

        $log = LogActividad::where('accion', AccionLogActividad::Modificacion)
            ->where('entidad_tipo', EntidadLogActividad::Factura)
            ->where('entidad_id', $factura->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($log);
    }

    public function test_factura_rectificacion_registra_evento(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'password' => bcrypt('secret123')]);
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        Serie::factory()->rectificativa()->for($tenant, 'tenant')->create();
        Serie::factory()->for($tenant, 'tenant')->create();

        $original = Factura::factory()->emitida()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
        ]);
        \App\Models\FacturaLinea::factory()->for($original)->create(['tenant_id' => $tenant->id]);
        \App\Models\FacturaImpuesto::factory()->for($original)->create(['tenant_id' => $tenant->id]);

        $this->loginAs($user);

        $this->post("/facturas/{$original->id}/rectificar", [
            'tipo_rectificacion' => 'sustitucion',
            'motivo_rectificacion' => 'Error en el tipo impositivo aplicado.',
        ]);

        $log = LogActividad::where('accion', AccionLogActividad::Modificacion)
            ->where('entidad_tipo', EntidadLogActividad::Factura)
            ->latest('id')
            ->first();
        $this->assertNotNull($log);
    }
}
