<?php

namespace Database\Seeders;

use App\Models\DepositType;
use Illuminate\Database\Seeder;

class DepositTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['Anticipo', 'Finiquito', 'Visita', 'Extras', 'Pago único'];

        foreach ($types as $type) {
            DepositType::firstOrCreate(['name' => $type]);
        }
    }
}
