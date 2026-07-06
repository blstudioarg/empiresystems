<?php

namespace Tests\Unit;

use App\Support\VerificadorVies;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VerificadorViesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_nif_iva_valido(): void
    {
        Http::fake([
            'ec.europa.eu/*' => Http::response(['valid' => true, 'name' => 'ACME SARL'], 200),
        ]);

        $resultado = VerificadorVies::verificar('FR12345678901', 'FR');

        $this->assertTrue($resultado['valido']);
        $this->assertTrue($resultado['verificado']);
        $this->assertSame('ACME SARL', $resultado['nombre']);
    }

    public function test_nif_iva_invalido(): void
    {
        Http::fake([
            'ec.europa.eu/*' => Http::response(['valid' => false], 200),
        ]);

        $resultado = VerificadorVies::verificar('FR00000000000', 'FR');

        $this->assertFalse($resultado['valido']);
        $this->assertTrue($resultado['verificado']);
    }

    public function test_vies_indisponible_degrada_sin_excepcion(): void
    {
        Http::fake([
            'ec.europa.eu/*' => Http::response(null, 500),
        ]);

        $resultado = VerificadorVies::verificar('FR12345678901', 'FR');

        $this->assertFalse($resultado['valido']);
        $this->assertFalse($resultado['verificado']);
    }

    public function test_timeout_de_vies_degrada_sin_excepcion(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timeout');
        });

        $resultado = VerificadorVies::verificar('FR12345678901', 'FR');

        $this->assertFalse($resultado['valido']);
        $this->assertFalse($resultado['verificado']);
    }

    public function test_resultado_se_cachea_por_nif_iva(): void
    {
        Http::fake([
            'ec.europa.eu/*' => Http::response(['valid' => true, 'name' => 'ACME SARL'], 200),
        ]);

        VerificadorVies::verificar('FR12345678901', 'FR');
        VerificadorVies::verificar('FR12345678901', 'FR');

        Http::assertSentCount(1);
    }
}
