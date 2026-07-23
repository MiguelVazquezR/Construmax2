<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `technicians` CHANGE `status` `status` ENUM('Activo', 'Inactivo', 'En revisión', 'Vetado', 'Eliminado') DEFAULT 'En revisión' NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `technicians` CHANGE `status` `status` ENUM('Activo', 'Inactivo', 'En revisión', 'Vetado') DEFAULT 'En revisión' NOT NULL");
    }
};