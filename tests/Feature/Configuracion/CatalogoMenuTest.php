<?php

namespace Tests\Feature\Configuracion;

use App\Support\CatalogoMenu;
use App\Support\CatalogoPermisos;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Invariantes de data-model.md §1 (INV-1 a INV-4): si un renombrado de ruta o de permiso deja el
 * catálogo inconsistente, esta red lo detecta antes de que una sección quede invisible en
 * silencio.
 */
class CatalogoMenuTest extends TestCase
{
    public function test_las_claves_son_unicas(): void
    {
        $claves = [];

        foreach (CatalogoMenu::catalogo() as $grupo) {
            $claves[] = $grupo['clave'];

            foreach ($grupo['hijos'] as $hijo) {
                $claves[] = $hijo['clave'];
            }
        }

        $this->assertSame(array_unique($claves), $claves, 'Las claves del catálogo deben ser únicas (INV-1).');
        $this->assertCount(36, $claves, 'El catálogo debe tener 36 elementos (10 grupos + 26 entradas, SC-002).');
    }

    public function test_toda_ruta_declarada_existe(): void
    {
        foreach (CatalogoMenu::catalogo() as $grupo) {
            if ($grupo['ruta'] !== null) {
                $this->assertTrue(Route::has($grupo['ruta']), "La ruta '{$grupo['ruta']}' del grupo '{$grupo['clave']}' no existe (INV-2).");
            }

            foreach ($grupo['hijos'] as $hijo) {
                $this->assertNotNull($hijo['ruta'], "La entrada '{$hijo['clave']}' debe declarar una ruta.");
                $this->assertTrue(Route::has($hijo['ruta']), "La ruta '{$hijo['ruta']}' de la entrada '{$hijo['clave']}' no existe (INV-2).");
            }
        }
    }

    public function test_todo_permiso_declarado_existe_en_el_catalogo_de_permisos(): void
    {
        $permisos = CatalogoPermisos::claves();

        foreach (CatalogoMenu::catalogo() as $grupo) {
            if ($grupo['permiso'] !== null) {
                $this->assertContains($grupo['permiso'], $permisos, "El permiso '{$grupo['permiso']}' del grupo '{$grupo['clave']}' no existe en CatalogoPermisos (INV-3).");
            }

            foreach ($grupo['hijos'] as $hijo) {
                $this->assertNotNull($hijo['permiso'], "La entrada '{$hijo['clave']}' debe declarar un permiso.");
                $this->assertContains($hijo['permiso'], $permisos, "El permiso '{$hijo['permiso']}' de la entrada '{$hijo['clave']}' no existe en CatalogoPermisos (INV-3).");
            }
        }
    }

    public function test_la_jerarquia_tiene_exactamente_dos_niveles(): void
    {
        foreach (CatalogoMenu::catalogo() as $grupo) {
            foreach ($grupo['hijos'] as $hijo) {
                $this->assertArrayHasKey('hijos', $hijo);
                $this->assertSame([], $hijo['hijos'], "La entrada '{$hijo['clave']}' no puede tener hijos propios (INV-4).");
            }
        }
    }

    public function test_planos_expone_todas_las_claves_y_existe_las_reconoce(): void
    {
        $planos = CatalogoMenu::planos();

        $this->assertCount(36, $planos);
        $this->assertArrayHasKey('clientes', $planos);
        $this->assertArrayHasKey('cartera-clientes', $planos);
        $this->assertTrue(CatalogoMenu::existe('facturas-crear'));
        $this->assertFalse(CatalogoMenu::existe('clave-inexistente'));
    }

    public function test_claves_grupos_devuelve_los_diez_grupos_de_primer_nivel(): void
    {
        $this->assertCount(10, CatalogoMenu::clavesGrupos());
        $this->assertContains('control-fichaje', CatalogoMenu::clavesGrupos());
    }
}
