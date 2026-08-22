<?php

namespace Tests\Unit;

use App\Support\CatalogoPermisos;
use PHPUnit\Framework\TestCase;

class CatalogoPermisosTest extends TestCase
{
    public function test_expone_las_34_claves_del_catalogo(): void
    {
        // 31 + `ver-pos-sala` y `ver-pos-opciones` del módulo de hostelería (feature 038)
        // + `ver-cobros` del módulo de Cobros (feature 043).
        $this->assertCount(34, CatalogoPermisos::claves());
    }

    public function test_no_hay_claves_duplicadas(): void
    {
        $claves = CatalogoPermisos::claves();

        $this->assertSame($claves, array_values(array_unique($claves)));
    }

    public function test_cada_permiso_tiene_clave_etiqueta_y_modulo(): void
    {
        foreach (CatalogoPermisos::todos() as $permiso) {
            $this->assertArrayHasKey('clave', $permiso);
            $this->assertArrayHasKey('etiqueta', $permiso);
            $this->assertArrayHasKey('modulo', $permiso);
            $this->assertNotEmpty($permiso['clave']);
        }
    }

    public function test_agrupacion_por_modulo_cubre_todas_las_claves(): void
    {
        $porModulo = CatalogoPermisos::porModulo();

        $clavesAgrupadas = [];
        foreach ($porModulo as $grupo) {
            $this->assertArrayHasKey('modulo', $grupo);
            $this->assertArrayHasKey('permisos', $grupo);
            foreach ($grupo['permisos'] as $permiso) {
                $clavesAgrupadas[] = $permiso['name'];
            }
        }

        sort($clavesAgrupadas);
        $todas = CatalogoPermisos::claves();
        sort($todas);

        $this->assertSame($todas, $clavesAgrupadas);
    }

    public function test_stock_agrupa_cuatro_permisos(): void
    {
        $porModulo = collect(CatalogoPermisos::porModulo());
        $stock = $porModulo->firstWhere('modulo', 'Stock');

        $this->assertNotNull($stock);
        $this->assertCount(4, $stock['permisos']);
    }

    public function test_control_de_fichaje_agrupa_siete_permisos(): void
    {
        $porModulo = collect(CatalogoPermisos::porModulo());
        $fichaje = $porModulo->firstWhere('modulo', 'Control de fichaje');

        $this->assertNotNull($fichaje);
        // Fichar, Mi jornada, Jornada, Calendario, Miembros, Horarios, Alertas (doc 09, Cambios 1 y 5).
        $this->assertCount(7, $fichaje['permisos']);
    }

    public function test_no_existe_el_permiso_fantasma_ver_bancos(): void
    {
        // Bancos se administra desde Configuración → Facturación, no es una vista de menú
        // (doc 09, Cambio 4).
        $this->assertNotContains('ver-bancos', CatalogoPermisos::claves());
    }

    public function test_usuario_base_excluye_permisos_de_gestion(): void
    {
        $base = CatalogoPermisos::clavesUsuarioBase();

        // Gestión de fichaje: vedada al rol base (Cambio 1). Fichar/Mi jornada sí las tiene (Cambio 5).
        $this->assertNotContains('ver-jornada', $base);
        $this->assertNotContains('ver-calendario', $base);
        $this->assertNotContains('ver-miembros', $base);
        $this->assertNotContains('ver-horarios', $base);
        $this->assertNotContains('ver-alertas', $base);
        $this->assertNotContains('ver-roles', $base);
        $this->assertNotContains('ver-usuarios', $base);
        $this->assertNotContains('ver-configuracion', $base);
        $this->assertNotContains('ver-logs', $base);
        $this->assertNotContains('ver-informes-equipo', $base);
        $this->assertContains('ver-fichar', $base);
        $this->assertContains('ver-mi-jornada', $base);
        $this->assertContains('ver-clientes', $base);
        $this->assertContains('ver-dashboard', $base);
        $this->assertContains('ver-informes-comerciales', $base);
        // Las subvistas de crear no se excluyen (el rol base ya podía crear vía el permiso de listado).
        $this->assertContains('ver-facturas-crear', $base);
        $this->assertContains('ver-pos-crear', $base);
        $this->assertContains('ver-campanas-crear', $base);
        // Sala y Opciones sí entran en el rol base: son operativa de sala, no gestión. Que un
        // usuario base las tenga no le da acceso si el tenant no activó el módulo (FR-003).
        $this->assertContains('ver-pos-sala', $base);
        $this->assertContains('ver-pos-opciones', $base);
        // ver-cobros no está excluido: todo usuario base con acceso a facturas también cobra.
        $this->assertContains('ver-cobros', $base);
        $this->assertCount(24, $base);
    }
}
