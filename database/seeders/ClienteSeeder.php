<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('nombre_comercial', 'Empresa Demo SL')->first();

        if (! $tenant) {
            return;
        }

        Cliente::firstOrCreate(
            ['tenant_id' => $tenant->id, 'nif' => 'B12345674'],
            [
                'tipo' => 'empresa',
                'nombre' => 'Contacto Comercial Demo',
                'razon_social' => 'Cliente Demo Empresa SL',
                'direccion' => 'Calle Mayor 1',
                'cp' => '28001',
                'ciudad' => 'Madrid',
                'provincia' => 'Madrid',
                'pais' => 'ES',
                'email' => 'contacto@clientedemo.es',
                'telefono' => '910000000',
            ]
        );

        Cliente::firstOrCreate(
            ['tenant_id' => $tenant->id, 'nombre' => 'Juan Pérez García'],
            [
                'tipo' => 'particular',
                'direccion' => 'Avenida de la Constitución 10',
                'cp' => '41001',
                'ciudad' => 'Sevilla',
                'provincia' => 'Sevilla',
                'pais' => 'ES',
                'email' => 'juan.perez@example.com',
                'telefono' => '600000000',
            ]
        );
    }
}
