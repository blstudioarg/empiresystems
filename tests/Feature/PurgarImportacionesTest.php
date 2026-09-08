<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgarImportacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_purga_borra_los_ficheros_de_mas_de_24h_y_conserva_los_recientes(): void
    {
        Storage::disk('local')->put('importaciones/reciente.xlsx', 'contenido');
        Storage::disk('local')->put('importaciones/antiguo.xlsx', 'contenido');

        $rutaAntigua = Storage::disk('local')->path('importaciones/antiguo.xlsx');
        touch($rutaAntigua, now()->subHours(25)->timestamp);

        $this->artisan('importaciones:purgar')->assertSuccessful();

        Storage::disk('local')->assertExists('importaciones/reciente.xlsx');
        Storage::disk('local')->assertMissing('importaciones/antiguo.xlsx');
    }

    /**
     * Feature 046: el borrador de una importación conversacional vive en la misma carpeta y con el
     * mismo token que el material, así que la purga se lo lleva sin saber que existe. Un material
     * abandonado no puede dejar datos personales de terceros ahí para siempre (FR-023, FR-024,
     * SC-006).
     */
    public function test_la_purga_se_lleva_tambien_el_borrador_del_asistente_sin_dejar_restos(): void
    {
        Storage::disk('local')->put('importaciones/abc.xlsx', 'contenido');
        Storage::disk('local')->put('importaciones/abc.borrador.json', '{"filas":[]}');

        foreach (['importaciones/abc.xlsx', 'importaciones/abc.borrador.json'] as $ruta) {
            touch(Storage::disk('local')->path($ruta), now()->subHours(25)->timestamp);
        }

        $this->artisan('importaciones:purgar')->assertSuccessful();

        $restos = array_values(array_filter(
            Storage::disk('local')->files('importaciones'),
            fn (string $ruta) => str_contains($ruta, 'abc'),
        ));

        $this->assertSame([], $restos, 'ni el material ni su borrador pueden sobrevivir a la purga');
    }
}
