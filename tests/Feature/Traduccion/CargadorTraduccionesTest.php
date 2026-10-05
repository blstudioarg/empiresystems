<?php

namespace Tests\Feature\Traduccion;

use App\Models\Tenant;
use App\Models\Traduccion;
use App\Models\TraduccionCorreccion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Research D1/D2: `__()` con el texto en español como clave. En español no se consulta nada y el
 * texto sale tal cual (SC-002); en chino sale de `traducciones`, con la corrección del tenant
 * activo por encima (FR-018), y todo el diccionario se carga con una consulta por tabla (SC-003).
 */
class CargadorTraduccionesTest extends TestCase
{
    use RefreshDatabase;

    private function consultasA(string $tabla, callable $accion): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $accion();
        $consultas = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains($q['query'], $tabla))->count();
        DB::disableQueryLog();

        return $consultas;
    }

    public function test_en_espanol_devuelve_el_texto_y_no_consulta_traducciones(): void
    {
        Traduccion::factory()->traducida('收款')->create(['texto' => 'Cobrar']);

        foreach ([null, 'es', 'en'] as $locale) {
            if ($locale) {
                app()->setLocale($locale);
            }

            $consultas = $this->consultasA('traduc', fn () => $this->assertSame('Cobrar', __('Cobrar')));
            $this->assertSame(0, $consultas, "locale {$locale}");
        }
    }

    public function test_en_chino_devuelve_la_traduccion_guardada(): void
    {
        Traduccion::factory()->traducida('收款')->create(['texto' => 'Cobrar']);

        app()->setLocale('zh');

        $this->assertSame('收款', __('Cobrar'));
    }

    public function test_una_fila_pendiente_o_con_error_se_muestra_en_espanol(): void
    {
        Traduccion::factory()->create(['texto' => 'Guardar cuenta', 'estado' => Traduccion::ESTADO_PENDIENTE]);
        Traduccion::factory()->create(['texto' => 'Aparcar', 'estado' => Traduccion::ESTADO_ERROR, 'traduccion' => null]);

        app()->setLocale('zh');

        $this->assertSame('Guardar cuenta', __('Guardar cuenta'));
        $this->assertSame('Aparcar', __('Aparcar'));
    }

    public function test_la_correccion_del_tenant_activo_prevalece_sobre_la_automatica(): void
    {
        $tenant = Tenant::factory()->create();
        Traduccion::factory()->traducida('收款')->create(['texto' => 'Cobrar']);
        TraduccionCorreccion::factory()->create([
            'tenant_id' => $tenant->id,
            'hash' => Traduccion::hashDe('Cobrar'),
            'texto' => 'Cobrar',
            'traduccion' => '结账',
        ]);

        tenancy()->initialize($tenant);
        app()->setLocale('zh');

        $this->assertSame('结账', __('Cobrar'));
    }

    public function test_las_variables_se_sustituyen_en_la_traduccion(): void
    {
        Traduccion::factory()->traducida(':mesa 号餐桌，:n 位用餐')->create(['texto' => 'Mesa :mesa · :n comensales']);

        app()->setLocale('zh');

        $this->assertSame('4 号餐桌，3 位用餐', __('Mesa :mesa · :n comensales', ['mesa' => '4', 'n' => 3]));
    }

    public function test_cargar_muchas_claves_hace_una_sola_consulta_por_tabla(): void
    {
        $tenant = Tenant::factory()->create();
        foreach (['Cobrar', 'Guardar', 'Mesa', 'Caja', 'Sala'] as $i => $texto) {
            Traduccion::factory()->traducida("译{$i}")->create(['texto' => $texto]);
        }

        tenancy()->initialize($tenant);
        app()->setLocale('zh');

        DB::flushQueryLog();
        DB::enableQueryLog();
        foreach (['Cobrar', 'Guardar', 'Mesa', 'Caja', 'Sala', 'No existe', 'Cobrar'] as $texto) {
            __($texto);
        }
        $consultas = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertSame(1, $consultas->filter(fn ($q) => str_contains($q, '"traducciones"'))->count());
        $this->assertSame(1, $consultas->filter(fn ($q) => str_contains($q, '"traduccion_correcciones"'))->count());
    }
}
