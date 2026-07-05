<?php

namespace Tests\Unit;

use App\Enums\RegimenImpositivo;
use App\Support\TiposImpositivos;
use PHPUnit\Framework\TestCase;

class TiposImpositivosPayloadTest extends TestCase
{
    public function test_payload_iva(): void
    {
        $payload = TiposImpositivos::payloadVista(RegimenImpositivo::Iva);

        $this->assertSame('iva', $payload['value']);
        $this->assertSame('IVA', $payload['label']);
        $this->assertNull($payload['tiposValidos']);
        $this->assertSame(21.0, $payload['tipoPorDefecto']);
        $this->assertTrue($payload['aplicaRecargo']);
    }

    public function test_payload_igic(): void
    {
        $payload = TiposImpositivos::payloadVista(RegimenImpositivo::Igic);

        $this->assertSame('igic', $payload['value']);
        $this->assertSame('IGIC', $payload['label']);
        $this->assertNull($payload['tiposValidos']);
        $this->assertSame(7.0, $payload['tipoPorDefecto']);
        $this->assertFalse($payload['aplicaRecargo']);
    }

    public function test_payload_ipsi(): void
    {
        $payload = TiposImpositivos::payloadVista(RegimenImpositivo::Ipsi);

        $this->assertSame('ipsi', $payload['value']);
        $this->assertSame('IPSI', $payload['label']);
        $this->assertNull($payload['tiposValidos']);
        $this->assertSame(0.0, $payload['tipoPorDefecto']);
        $this->assertFalse($payload['aplicaRecargo']);
    }
}
