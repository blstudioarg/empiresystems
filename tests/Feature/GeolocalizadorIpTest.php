<?php

namespace Tests\Feature;

use App\Support\GeolocalizadorIp;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeolocalizadorIpTest extends TestCase
{
    public function test_ip_privada_no_llama_a_la_api_y_devuelve_null(): void
    {
        Http::fake();

        $this->assertNull(GeolocalizadorIp::ubicacion('192.168.1.10'));
        $this->assertNull(GeolocalizadorIp::ubicacion('127.0.0.1'));
        $this->assertNull(GeolocalizadorIp::ubicacion(null));

        Http::assertNothingSent();
    }

    public function test_ip_publica_devuelve_ciudad_y_pais(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'city' => 'Madrid',
                'country' => 'Spain',
            ]),
        ]);

        $this->assertSame('Madrid, Spain', GeolocalizadorIp::ubicacion('203.0.113.10'));
    }

    public function test_respuesta_de_la_api_se_cachea_por_ip(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response(['status' => 'success', 'city' => 'Madrid', 'country' => 'Spain']),
        ]);

        GeolocalizadorIp::ubicacion('203.0.113.20');
        GeolocalizadorIp::ubicacion('203.0.113.20');
        GeolocalizadorIp::ubicacion('203.0.113.20');

        Http::assertSentCount(1);
    }

    public function test_fallo_de_la_api_no_rompe_y_devuelve_null(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([], 500),
        ]);

        $this->assertNull(GeolocalizadorIp::ubicacion('203.0.113.30'));
    }

    public function test_timeout_no_rompe_y_devuelve_null(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timed out');
        });

        $this->assertNull(GeolocalizadorIp::ubicacion('203.0.113.40'));
    }
}
