<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LordiconGet extends Command
{
    protected $signature = 'lordicon:get
        {search? : Texto a buscar (nombre o título del ícono)}
        {--family= : Filtrar por familia (system, wired, etc.)}
        {--style= : Filtrar por estilo (regular, solid, flat, outline...)}
        {--premium : Solo íconos premium}
        {--index= : Índice exacto del ícono dentro de familia+estilo}
        {--force : Volver a descargar aunque ya exista localmente}';

    protected $description = 'Busca un ícono en la API de Lordicon y descarga su JSON localmente (cache en public/icons/lordicon)';

    private const API_BASE = 'https://api.lordicon.com';

    private const STORAGE_DIR = 'icons/lordicon';

    public function handle(): int
    {
        $token = config('services.lordicon.token');

        if (! $token) {
            $this->error('Falta LORDICON_API_TOKEN en el .env.');

            return self::FAILURE;
        }

        $query = array_filter([
            'search' => $this->argument('search'),
            'family' => $this->option('family'),
            'style' => $this->option('style'),
            'premium' => $this->option('premium') ? 'true' : null,
            'index' => $this->option('index'),
        ]);

        if (empty($query)) {
            $this->error('Indicá al menos un término de búsqueda o un filtro (--family, --style, --index).');

            return self::FAILURE;
        }

        $response = Http::withToken($token)->get(self::API_BASE.'/v1/icons', $query);

        if ($response->status() === 429) {
            $this->error('Rate limit de Lordicon superado. Probá de nuevo en unos minutos.');

            return self::FAILURE;
        }

        if ($response->failed()) {
            $this->error("Error de la API de Lordicon ({$response->status()}): {$response->body()}");

            return self::FAILURE;
        }

        $icons = $response->json();

        if (empty($icons)) {
            $this->warn('No se encontraron íconos con esos criterios.');

            return self::SUCCESS;
        }

        $icon = count($icons) === 1 ? $icons[0] : $this->pickIcon($icons);

        if (! $icon) {
            return self::SUCCESS;
        }

        return $this->downloadIcon($icon);
    }

    /**
     * @param  array<int, array<string, mixed>>  $icons
     * @return array<string, mixed>|null
     */
    private function pickIcon(array $icons): ?array
    {
        $labels = [];

        foreach ($icons as $i => $icon) {
            $premiumTag = $icon['premium'] ? 'premium' : 'free';
            $labels[$i] = "{$icon['family']}/{$icon['style']}/{$icon['index']} — {$icon['title']} ({$premiumTag})";
        }

        $this->info(count($icons).' resultados encontrados:');
        $choice = $this->choice('¿Cuál querés descargar?', $labels);

        $selectedIndex = array_search($choice, $labels, true);

        return $selectedIndex === false ? null : $icons[$selectedIndex];
    }

    /**
     * @param  array<string, mixed>  $icon
     */
    private function downloadIcon(array $icon): int
    {
        if (empty($icon['files']['json'])) {
            $this->error("El ícono '{$icon['name']}' no incluye link de JSON: o no está en tu plan, o es solo preview.");

            return self::FAILURE;
        }

        $filename = sprintf(
            '%s-%s-%d-%s.json',
            $icon['family'],
            $icon['style'],
            $icon['index'],
            Str::slug($icon['name'])
        );

        $relativePath = self::STORAGE_DIR.'/'.$filename;
        $publicPath = public_path($relativePath);

        if (File::exists($publicPath) && ! $this->option('force')) {
            $this->info("Ya lo tenemos descargado: public/{$relativePath}");
            $this->line('Usalo con: '.asset($relativePath));

            return self::SUCCESS;
        }

        $jsonResponse = Http::get($icon['files']['json']);

        if ($jsonResponse->failed()) {
            $this->error('No se pudo descargar el JSON del ícono (el link firmado puede haber expirado, reintentá).');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($publicPath));
        File::put($publicPath, $jsonResponse->body());

        // Reporta la descarga a Lordicon (billing), solo cuando es una descarga real (no en cache-hit).
        Http::withToken(config('services.lordicon.token'))->post(self::API_BASE.'/v1/download/track', [
            'family' => $icon['family'],
            'style' => $icon['style'],
            'index' => $icon['index'],
        ]);

        $this->updateManifest($icon, $relativePath);

        $this->info("Descargado: public/{$relativePath}");
        $this->line('Usalo con: '.asset($relativePath));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $icon
     */
    private function updateManifest(array $icon, string $relativePath): void
    {
        $manifestPath = public_path(self::STORAGE_DIR.'/manifest.json');

        $manifest = File::exists($manifestPath)
            ? json_decode(File::get($manifestPath), true)
            : [];

        $key = "{$icon['family']}/{$icon['style']}/{$icon['index']}";

        $manifest[$key] = [
            'family' => $icon['family'],
            'style' => $icon['style'],
            'index' => $icon['index'],
            'name' => $icon['name'],
            'title' => $icon['title'],
            'premium' => $icon['premium'],
            'file' => $relativePath,
        ];

        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
