<?php

namespace Tests\Unit;

use App\Excel\Definiciones\DefinicionClientes;
use App\Exceptions\ImportacionInvalidaException;
use App\Services\InterpretadorMaterialImportable;
use App\Support\ContadorPaginasPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La interpretación se prueba sin red ni clave de API sustituyendo el único punto que habla con el
 * proveedor (research D10, mismo patrón que la 044 y `CompactadorConversacion`).
 *
 * Lo que se está protegiendo aquí es el riesgo más serio de la feature: un NIF inventado entra en el
 * maestro de clientes y nadie lo detecta nunca (FR-009).
 */
class InterpretadorMaterialTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $lectura
     */
    private function interprete(array $lectura): InterpretadorMaterialImportable
    {
        return new class($lectura) extends InterpretadorMaterialImportable
        {
            public function __construct(private array $lectura)
            {
                parent::__construct(new ContadorPaginasPdf);
            }

            protected function pedirLectura(string $contenido, string $nombre, string $extension, array $schema, string $instrucciones): array
            {
                return $this->lectura;
            }
        };
    }

    private function ficheroTemporal(string $contenido = 'da igual'): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'material_').'.txt';
        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    /**
     * FR-009: un campo que el documento no dice llega **vacío y marcado como no leído**, nunca
     * relleno con un valor plausible.
     */
    public function test_un_campo_ilegible_llega_vacio_y_no_relleno(): void
    {
        $interprete = $this->interprete([
            'registros' => [
                ['tipo' => 'empresa', 'nombre' => 'Acme', 'razon_social' => 'Acme SL', 'nif' => null],
            ],
            'avisos' => [],
        ]);

        $resultado = $interprete->interpretar(new DefinicionClientes, $this->ficheroTemporal(), 'listado.txt', 'txt');

        $fila = $resultado['filas'][0];

        $this->assertSame('Acme', $fila['datos']['nombre']);
        $this->assertNull($fila['datos']['nif'], 'un NIF que el documento no dice no se puede rellenar');
        $this->assertFalse($fila['leido']['nif'], 'y tiene que quedar constancia de que no se leyó');
        $this->assertTrue($fila['leido']['nombre']);
    }

    public function test_un_documento_sin_registros_se_reporta_como_tal(): void
    {
        $interprete = $this->interprete(['registros' => [], 'avisos' => ['El documento no parece un listado de clientes.']]);

        $resultado = $interprete->interpretar(new DefinicionClientes, $this->ficheroTemporal(), 'foto.txt', 'txt');

        $this->assertSame([], $resultado['filas']);
        $this->assertNotEmpty($resultado['avisos']);
    }

    public function test_lo_que_no_se_pudo_leer_se_conserva_para_contarlo(): void
    {
        $interprete = $this->interprete([
            'registros' => [['tipo' => 'particular', 'nombre' => 'Juan']],
            'avisos' => ['La página 3 está borrosa y no se pudo leer.'],
        ]);

        $resultado = $interprete->interpretar(new DefinicionClientes, $this->ficheroTemporal(), 'listado.txt', 'txt');

        $this->assertSame(['La página 3 está borrosa y no se pudo leer.'], $resultado['avisos']);
    }

    /**
     * Las claves de salida son las internas de `ColumnaExcel`: es lo que permite que el material
     * interpretado entre en el mismo pipeline que un Excel, sin traducción intermedia.
     */
    public function test_las_filas_salen_con_las_claves_internas_del_modulo(): void
    {
        $interprete = $this->interprete([
            'registros' => [['tipo' => 'particular', 'nombre' => 'Juan']],
            'avisos' => [],
        ]);

        $resultado = $interprete->interpretar(new DefinicionClientes, $this->ficheroTemporal(), 'listado.txt', 'txt');

        $claves = array_keys($resultado['filas'][0]['datos']);

        $this->assertContains('aplica_recargo_equivalencia', $claves);
        $this->assertNotContains('recargo_de_equivalencia', $claves, 'esa es la cabecera del fichero, no la clave interna');
    }

    /**
     * Edge case del spec: sin clave de IA configurada, un documento se rechaza con explicación —los
     * ficheros estructurados no pasan por aquí y siguen funcionando.
     */
    public function test_sin_clave_de_ia_se_rechaza_con_explicacion_y_no_con_un_error_tecnico(): void
    {
        $interprete = new InterpretadorMaterialImportable(new ContadorPaginasPdf);

        $this->expectException(ImportacionInvalidaException::class);
        $this->expectExceptionMessageMatches('/asistente|clave/i');

        $interprete->interpretar(new DefinicionClientes, $this->ficheroTemporal(), 'listado.txt', 'txt');
    }

    /**
     * research D9: el coste crece con el documento, no con las filas que salen de él.
     */
    public function test_un_pdf_por_encima_del_tope_de_paginas_se_rechaza_explicando_el_limite(): void
    {
        config()->set('importacion.material.max_paginas', 2);

        $interprete = new class extends InterpretadorMaterialImportable
        {
            public function __construct()
            {
                parent::__construct(new class extends ContadorPaginasPdf
                {
                    public function contar(string $rutaAbsoluta): ?int
                    {
                        return 40;
                    }
                });
            }

            protected function pedirLectura(string $contenido, string $nombre, string $extension, array $schema, string $instrucciones): array
            {
                throw new \LogicException('no debería llegar a llamar al proveedor');
            }
        };

        $this->expectException(ImportacionInvalidaException::class);
        $this->expectExceptionMessageMatches('/40|2 páginas|páginas/u');

        $interprete->interpretar(new DefinicionClientes, $this->ficheroTemporal(), 'listado.pdf', 'pdf');
    }
}
