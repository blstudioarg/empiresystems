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
}
