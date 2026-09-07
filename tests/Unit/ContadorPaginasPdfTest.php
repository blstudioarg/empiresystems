<?php

namespace Tests\Unit;

use App\Support\ContadorPaginasPdf;
use PHPUnit\Framework\TestCase;

class ContadorPaginasPdfTest extends TestCase
{
    private string $temporal;

    protected function tearDown(): void
    {
        if (isset($this->temporal) && is_file($this->temporal)) {
            unlink($this->temporal);
        }

        parent::tearDown();
    }

    private function ficheroCon(string $contenido, string $extension = 'pdf'): string
    {
        $this->temporal = tempnam(sys_get_temp_dir(), 'pdf').'.'.$extension;
        file_put_contents($this->temporal, $contenido);

        return $this->temporal;
    }

    public function test_cuenta_un_pdf_de_una_pagina(): void
    {
        $ruta = $this->ficheroCon("%PDF-1.4\n1 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n");

        $this->assertSame(1, (new ContadorPaginasPdf)->contar($ruta));
    }

    public function test_cuenta_un_pdf_de_varias_paginas(): void
    {
        $ruta = $this->ficheroCon(
            "%PDF-1.4\n"
            ."1 0 obj\n<< /Type /Page /Parent 9 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Page /Parent 9 0 R >>\nendobj\n"
            ."3 0 obj\n<< /Type /Page /Parent 9 0 R >>\nendobj\n"
        );

        $this->assertSame(3, (new ContadorPaginasPdf)->contar($ruta));
    }

    public function test_prefiere_el_count_declarado_del_nodo_de_paginas(): void
    {
        $ruta = $this->ficheroCon(
            "%PDF-1.4\n"
            ."9 0 obj\n<< /Type /Pages /Kids [1 0 R 2 0 R] /Count 7 >>\nendobj\n"
            ."1 0 obj\n<< /Type /Page /Parent 9 0 R >>\nendobj\n"
        );

        $this->assertSame(7, (new ContadorPaginasPdf)->contar($ruta));
    }

    public function test_el_nodo_pages_no_se_cuenta_como_una_pagina_mas(): void
    {
        $ruta = $this->ficheroCon(
            "%PDF-1.4\n"
            ."9 0 obj\n<< /Type /Pages /Kids [1 0 R] >>\nendobj\n"
            ."1 0 obj\n<< /Type /Page /Parent 9 0 R >>\nendobj\n"
        );

        $this->assertSame(1, (new ContadorPaginasPdf)->contar($ruta));
    }

    /**
     * research D7: si no puede determinarse, no se bloquea la subida — devuelve null, no lanza.
     */
    public function test_un_fichero_que_no_es_pdf_devuelve_null(): void
    {
        $ruta = $this->ficheroCon('esto no es un pdf', 'txt');

        $this->assertNull((new ContadorPaginasPdf)->contar($ruta));
    }

    public function test_un_pdf_sin_paginas_en_claro_devuelve_null(): void
    {
        $ruta = $this->ficheroCon("%PDF-1.7\n<< /ObjStm comprimido >>\n");

        $this->assertNull((new ContadorPaginasPdf)->contar($ruta));
    }

    public function test_un_fichero_inexistente_devuelve_null(): void
    {
        $this->assertNull((new ContadorPaginasPdf)->contar('/ruta/que/no/existe.pdf'));
    }
}
