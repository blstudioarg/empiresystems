<?php

namespace Tests\Unit;

use App\Support\CatalogoPermisos;
use PHPUnit\Framework\TestCase;

class CatalogoPermisosTest extends TestCase
{
    public function test_expone_las_21_claves_del_catalogo(): void
    {
        $this->assertCount(21, CatalogoPermisos::claves());
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

    public function test_usuario_base_excluye_permisos_de_gestion(): void
    {
        $base = CatalogoPermisos::clavesUsuarioBase();

        $this->assertNotContains('ver-jornada', $base);
        $this->assertNotContains('ver-roles', $base);
        $this->assertNotContains('ver-usuarios', $base);
        $this->assertNotContains('ver-configuracion', $base);
        $this->assertNotContains('ver-logs', $base);
        $this->assertContains('ver-clientes', $base);
        $this->assertContains('ver-dashboard', $base);
        $this->assertCount(16, $base);
    }
}
