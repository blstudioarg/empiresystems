<?php

namespace Tests\Unit;

use App\Support\AgenteUsuario;
use PHPUnit\Framework\TestCase;

class AgenteUsuarioTest extends TestCase
{
    public function test_null_devuelve_null(): void
    {
        $this->assertNull(AgenteUsuario::label(null));
    }

    public function test_chrome_en_windows(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        $this->assertSame('Chrome en Windows', AgenteUsuario::label($ua));
    }

    public function test_firefox_en_linux(): void
    {
        $ua = 'Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0';

        $this->assertSame('Firefox en Linux', AgenteUsuario::label($ua));
    }

    public function test_safari_en_macos(): void
    {
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';

        $this->assertSame('Safari en macOS', AgenteUsuario::label($ua));
    }

    public function test_edge_en_windows(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0';

        $this->assertSame('Edge en Windows', AgenteUsuario::label($ua));
    }

    public function test_chrome_en_android(): void
    {
        $ua = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';

        $this->assertSame('Chrome en Android', AgenteUsuario::label($ua));
    }

    public function test_user_agent_no_reconocido_devuelve_etiquetas_por_defecto(): void
    {
        $this->assertSame('Navegador desconocido en sistema desconocido', AgenteUsuario::label('curl/8.4.0'));
    }
}
