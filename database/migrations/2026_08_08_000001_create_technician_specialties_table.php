<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_specialties', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed with the current master list so existing installs get the catalog populated
        DB::table('technician_specialties')->insert([
            ['name' => 'Electricidad baja tensión', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Electricidad alta tensión', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Plomería / Fontanería', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Aire acondicionado (HVAC)', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Tablaroca y acabados', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Pintura general', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Impermeabilización', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Albañilería', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Herrería y soldadura', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Vidrio y aluminio', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Redes y voz/datos', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Cerrajería', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Limpieza industrial', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Carpintería', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Pisos y azulejos', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Jardinería', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Fumigación y plagas', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Instalación de cámaras (CCTV)', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Domótica', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mantenimiento de elevadores', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_specialties');
    }
};