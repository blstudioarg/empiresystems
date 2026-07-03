<?php

namespace Database\Seeders;

use App\Models\Localidad;
use App\Models\Provincia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ProvinciaLocalidadSeeder extends Seeder
{
    /**
     * Carga el catálogo oficial de provincias y municipios del INE (dataset
     * codeforspain/ds-organizacion-administrativa, relación a 1 de enero de 2026).
     */
    public function run(): void
    {
        $now = Carbon::now();

        $provincias = $this->readCsv(__DIR__.'/data/provincias.csv');
        $provinciaRows = array_map(fn (array $row) => [
            'id' => $row['provincia_id'],
            'nombre' => $row['nombre'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $provincias);
        Provincia::query()->upsert($provinciaRows, ['id']);

        $municipios = $this->readCsv(__DIR__.'/data/municipios.csv');
        $localidadRows = array_map(fn (array $row) => [
            'id' => $row['municipio_id'],
            'provincia_id' => $row['provincia_id'],
            'nombre' => $row['nombre'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $municipios);

        foreach (array_chunk($localidadRows, 500) as $chunk) {
            Localidad::query()->upsert($chunk, ['id']);
        }
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, $line);
        }

        fclose($handle);

        return $rows;
    }
}
