<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technicians', function (Blueprint $table) {
            $table->id();
            
            // Relación con el usuario (Acceso, nombre, foto)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Datos Generales
            $table->string('phone')->nullable(); // Teléfono principal
            $table->string('secondary_phone')->nullable();
            $table->boolean('is_internal')->default(false); // Interno vs Externo
            
            // Geolocalización Operativa
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('colony')->nullable();
            $table->string('zip_code')->nullable();
            $table->integer('coverage_radius_km')->default(10); // Kilómetros
            
            // Especialidades (Guardadas como JSON para filtrado rápido: ["Plomería", "Electricidad"])
            $table->json('specialties')->nullable();
            
            // Datos Fiscales
            $table->string('legal_name')->nullable(); // Razón Social
            $table->string('rfc')->nullable();
            
            // Datos Bancarios (JSON para flexibilidad o campos directos)
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable(); // Cuenta / Tarjeta
            $table->string('clabe')->nullable();
            
            // Estatus del Técnico (Diferente al User::is_active)
            // 'Activo': Listo para trabajar
            // 'Inactivo': Temporalmente fuera
            // 'En revisión': Documentación pendiente
            // 'Vetado': Lista negra
            $table->enum('status', ['Activo', 'Inactivo', 'En revisión', 'Vetado'])->default('En revisión');
            
            // Sistema de Calificación
            $table->decimal('rating_avg', 3, 2)->default(0.00); // Promedio 1.00 a 5.00
            
            // Retroalimentación interna
            $table->text('internal_notes')->nullable(); // Bitácora de comportamiento

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technicians');
    }
};