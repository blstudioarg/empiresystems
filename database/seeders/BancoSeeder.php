<?php

namespace Database\Seeders;

use App\Models\Banco;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class BancoSeeder extends Seeder
{
    /**
     * Catálogo de partida con las principales entidades bancarias que operan en España.
     * Ahora los bancos son tenant-dependientes (cada tenant gestiona los suyos), así que este
     * seed solo siembra el catálogo del PRIMER tenant existente. El resto de tenants parten
     * vacíos y crean sus propios bancos desde la app. Idempotente vía `firstOrCreate`.
     */
    public function run(): void
    {
        $tenant = Tenant::orderBy('id')->first();

        if (! $tenant) {
            return;
        }

        $bancos = [
            'Banco Santander',
            'BBVA',
            'CaixaBank',
            'Banco Sabadell',
            'Bankinter',
            'ING',
            'Unicaja Banco',
            'Abanca',
            'Kutxabank',
            'Ibercaja',
            'Cajamar',
            'Openbank',
            'EVO Banco',
            'Deutsche Bank España',
        ];

        foreach ($bancos as $nombre) {
            Banco::firstOrCreate([
                'tenant_id' => $tenant->id,
                'nombre' => $nombre,
            ]);
        }
    }
}
