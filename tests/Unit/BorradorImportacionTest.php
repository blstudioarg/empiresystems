<?php

namespace Tests\Unit;

use App\Excel\BorradorImportacion;
use App\Exceptions\ImportacionInvalidaException;
use App\Support\MaterialImportable;
use Tests\TestCase;

/**
 * Test-first sobre la corrección de filas (research D10). Aplicar una corrección a la fila
 * equivocada, o darla por aplicada sin estarlo, es el error más difícil de detectar de la feature:
 * no rompe nada, solo deja el dato mal.
 */
class BorradorImportacionTest extends TestCase
{
    private function borrador(): BorradorImportacion
    {
        return new BorradorImportacion(
            token: 'tok-1',
            tenantId: 1,
            userId: 7,
            conversacionId: 3,
            modulo: 'clientes',
            origen: MaterialImportable::ORIGEN_HOJA,
            filas: [
                ['indice' => 0, 'datos' => ['nombre' => 'Acme', 'nif' => null, 'email' => 'a@acme.es'], 'leido' => []],
                ['indice' => 1, 'datos' => ['nombre' => 'Beta', 'nif' => null, 'email' => null], 'leido' => []],
                ['indice' => 2, 'datos' => ['nombre' => 'Ceta', 'nif' => 'B12345674', 'email' => null], 'leido' => []],
            ],
        );
    }

    public function test_una_correccion_cambia_solo_esa_fila_y_ese_campo(): void
    {
        $borrador = $this->borrador();

        $borrador->corregir(0, 'nif', 'B12345674');

        $filas = $borrador->filas();

        $this->assertSame('B12345674', $filas[0]['datos']['nif']);
        $this->assertSame('Acme', $filas[0]['datos']['nombre'], 'no puede tocar otro campo de la misma fila');
        $this->assertSame('a@acme.es', $filas[0]['datos']['email']);
        $this->assertNull($filas[1]['datos']['nif'], 'no puede tocar otra fila');
        $this->assertSame('B12345674', $filas[2]['datos']['nif']);
    }

    public function test_la_correccion_queda_registrada_para_poder_explicarla(): void
    {
        $borrador = $this->borrador();
        $borrador->corregir(1, 'email', 'beta@beta.es');

        $this->assertSame(
            [['indice' => 1, 'campo' => 'email', 'valor' => 'beta@beta.es']],
            $borrador->correcciones(),
        );
    }

    public function test_descartar_quita_la_fila_del_recuento_pero_no_reindexa_el_resto(): void
    {
        $borrador = $this->borrador();

        $borrador->descartar(1);

        $this->assertSame([1], $borrador->descartadas());
        $this->assertCount(2, $borrador->filasVigentes());
        $this->assertSame([0, 2], array_column($borrador->filasVigentes(), 'indice'));
        $this->assertCount(3, $borrador->filas(), 'la fila descartada sigue en el borrador, solo queda fuera');
    }

    public function test_descartar_dos_veces_la_misma_fila_no_la_cuenta_dos_veces(): void
    {
        $borrador = $this->borrador();

        $borrador->descartar(1);
        $borrador->descartar(1);

        $this->assertSame([1], $borrador->descartadas());
    }

    /**
     * Contrato §4: el invariante más importante del borrador.
     */
    public function test_corregir_un_indice_inexistente_falla_explicitamente(): void
    {
        $borrador = $this->borrador();

        $this->expectException(ImportacionInvalidaException::class);
        $this->expectExceptionMessageMatches('/9/');

        $borrador->corregir(9, 'nif', 'B12345674');
    }

    public function test_corregir_un_campo_inexistente_falla_explicitamente(): void
    {
        $borrador = $this->borrador();

        $this->expectException(ImportacionInvalidaException::class);
        $this->expectExceptionMessageMatches('/telefono/');

        $borrador->corregir(0, 'telefono', '600123456');
    }

    public function test_descartar_un_indice_inexistente_falla_explicitamente(): void
    {
        $borrador = $this->borrador();

        $this->expectException(ImportacionInvalidaException::class);

        $borrador->descartar(9);
    }

    /**
     * Corregir un campo que el documento no dijo lo marca como leído: deja de ser "el documento no
     * lo dice" y pasa a ser un dato aportado por la persona (FR-009).
     */
    public function test_corregir_un_campo_no_leido_lo_marca_como_aportado(): void
    {
        $borrador = new BorradorImportacion(
            token: 'tok-2', tenantId: 1, userId: 7, conversacionId: null,
            modulo: 'clientes', origen: MaterialImportable::ORIGEN_DOCUMENTO,
            filas: [['indice' => 0, 'datos' => ['nombre' => 'Acme', 'nif' => null], 'leido' => ['nombre' => true, 'nif' => false]]],
        );

        $borrador->corregir(0, 'nif', 'B12345674');

        $this->assertTrue($borrador->filas()[0]['leido']['nif']);
    }

    public function test_sobrevive_a_la_ida_y_vuelta_del_almacenamiento(): void
    {
        $borrador = $this->borrador();
        $borrador->corregir(0, 'nif', 'B12345674');
        $borrador->descartar(2);

        $revivido = BorradorImportacion::desdeArray($borrador->toArray());

        $this->assertSame($borrador->token, $revivido->token);
        $this->assertSame($borrador->tenantId, $revivido->tenantId);
        $this->assertSame($borrador->userId, $revivido->userId);
        $this->assertSame($borrador->conversacionId, $revivido->conversacionId);
        $this->assertSame($borrador->modulo, $revivido->modulo);
        $this->assertSame($borrador->origen, $revivido->origen);
        $this->assertSame($borrador->filas(), $revivido->filas());
        $this->assertSame($borrador->descartadas(), $revivido->descartadas());
        $this->assertSame($borrador->correcciones(), $revivido->correcciones());
    }

    /**
     * US3 escenario 4: acumular varios documentos en la misma importación. Los índices que ya
     * existen no se pueden reutilizar o las correcciones dictadas antes apuntarían a otra fila.
     */
    public function test_acumular_filas_continua_la_numeracion_en_vez_de_reiniciarla(): void
    {
        $borrador = $this->borrador();

        $borrador->acumular([
            ['datos' => ['nombre' => 'Delta', 'nif' => null, 'email' => null], 'leido' => []],
        ]);

        $filas = $borrador->filas();

        $this->assertCount(4, $filas);
        $this->assertSame([0, 1, 2, 3], array_column($filas, 'indice'));
        $this->assertSame('Delta', $filas[3]['datos']['nombre']);
    }
}
