<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Iluminación',
            'Herrería',
            'Acabados',
            'Eléctrico',
            'Aire acondicionado',
            'Sanitario',
            'Anuncios',
            'Pintura',
            'Carpintería',
            'Vidrio',
            'Aluminio',
            'Protección civil STPS',
            'Monta cargas',
            'Control de plagas',
            'Impermeabilización',
            'Servicios varios',
        ];

        foreach ($types as $type) {
            DB::table('service_types')->insertOrIgnore([
                'name' => $type,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
