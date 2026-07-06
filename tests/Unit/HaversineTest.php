<?php

namespace Tests\Unit;

use App\Support\Haversine;
use PHPUnit\Framework\TestCase;

class HaversineTest extends TestCase
{
    public function test_distancia_cero_entre_el_mismo_punto(): void
    {
        $this->assertSame(0, Haversine::metros(40.4168, -3.7038, 40.4168, -3.7038));
    }

    /**
     * Un grado de latitud (norte-sur, misma longitud) equivale a R * (pi/180) radianes ≈
     * 111.194,93 m, con R = 6.371.000 m (radio terrestre medio usado por la fórmula).
     */
    public function test_distancia_conocida_un_grado_de_latitud(): void
    {
        $esperado = 111194.93;

        $distancia = Haversine::metros(0.0, 0.0, 1.0, 0.0);

        $this->assertEqualsWithDelta($esperado, $distancia, $esperado * 0.01);
    }

    /**
     * Madrid (Puerta del Sol) → Barcelona (Plaça Catalunya): distancia tabulada ≈ 504,6 km.
     */
    public function test_distancia_conocida_madrid_barcelona(): void
    {
        $esperado = 504_600;

        $distancia = Haversine::metros(40.4169, -3.7035, 41.3874, 2.1701);

        $this->assertEqualsWithDelta($esperado, $distancia, $esperado * 0.01);
    }
}
