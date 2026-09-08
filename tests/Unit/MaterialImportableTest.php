<?php

namespace Tests\Unit;

use App\Support\MaterialImportable;
use Tests\TestCase;

class MaterialImportableTest extends TestCase
{
    public static function tiposDeHoja(): array
    {
        return [['xlsx'], ['xls'], ['csv']];
    }

    public static function tiposDeDocumento(): array
    {
        return [['pdf'], ['jpg'], ['jpeg'], ['png'], ['webp'], ['txt']];
    }

    /**
     * @dataProvider tiposDeHoja
     */
    public function test_las_hojas_de_calculo_se_leen_sin_pasar_por_el_proveedor(string $extension): void
    {
        $this->assertTrue(MaterialImportable::admitido($extension));
        $this->assertSame(MaterialImportable::ORIGEN_HOJA, MaterialImportable::origen($extension));
        $this->assertFalse(MaterialImportable::esDocumento($extension));
    }

    /**
     * @dataProvider tiposDeDocumento
     */
    public function test_lo_no_tabular_cae_en_documento_y_hay_que_interpretarlo(string $extension): void
    {
        $this->assertTrue(MaterialImportable::admitido($extension));
        $this->assertSame(MaterialImportable::ORIGEN_DOCUMENTO, MaterialImportable::origen($extension));
        $this->assertTrue(MaterialImportable::esDocumento($extension));
    }

    public function test_la_extension_se_normaliza_en_mayusculas_y_con_punto(): void
    {
        $this->assertSame(MaterialImportable::ORIGEN_HOJA, MaterialImportable::origen('.XLSX'));
        $this->assertSame(MaterialImportable::ORIGEN_DOCUMENTO, MaterialImportable::origen('PDF'));
    }

    /**
     * FR-003: un tipo no admitido da una explicación, nunca una excepción cruda ni un 500.
     */
    public function test_un_tipo_no_admitido_da_un_mensaje_explicativo_y_no_una_excepcion(): void
    {
        $this->assertFalse(MaterialImportable::admitido('exe'));
        $this->assertNull(MaterialImportable::origen('exe'));

        $motivo = MaterialImportable::motivoRechazo('exe');

        $this->assertStringContainsString('.exe', $motivo);
        $this->assertStringContainsString('xlsx', $motivo, 'el rechazo debe decir qué SÍ se admite');
        $this->assertStringNotContainsString('Exception', $motivo);
    }

    public function test_un_fichero_sin_extension_tambien_se_rechaza_con_explicacion(): void
    {
        $this->assertFalse(MaterialImportable::admitido(''));
        $this->assertStringContainsString('extensión', MaterialImportable::motivoRechazo(''));
    }

    /**
     * Quitar un tipo de la config lo desactiva; añadir uno que el sistema no sabe leer, no lo
     * habilita: la lista de config es un filtro, no una fuente de capacidades.
     */
    public function test_la_config_acota_pero_no_inventa_capacidades(): void
    {
        config()->set('importacion.material.tipos', ['xlsx', 'docx']);

        $this->assertSame(['xlsx'], MaterialImportable::tiposAdmitidos());
        $this->assertFalse(MaterialImportable::admitido('pdf'));
        $this->assertFalse(MaterialImportable::admitido('docx'));
    }

    public function test_el_tope_de_paginas_sale_de_config_y_nunca_es_cero(): void
    {
        config()->set('importacion.material.max_paginas', 5);
        $this->assertSame(5, MaterialImportable::maxPaginas());

        config()->set('importacion.material.max_paginas', 0);
        $this->assertSame(1, MaterialImportable::maxPaginas());
    }
}
