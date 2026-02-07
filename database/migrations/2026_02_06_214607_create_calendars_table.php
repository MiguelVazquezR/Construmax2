<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla de Eventos / Tareas
        Schema::create('calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->comment('Creador del evento');
            
            $table->string('type'); // Evento, Tarea, Reunión, etc.
            $table->string('title'); // Motivo
            $table->text('description')->nullable();
            
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            
            $table->timestamps();
        });

        // Tabla Pivote para Participantes e Invitaciones
        Schema::create('calendar_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained(); // Usuario invitado
            
            $table->string('status')->default('Pendiente'); // Pendiente, Aceptado, Rechazado
            $table->string('rejection_reason')->nullable(); // Motivo de rechazo
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_participants');
        Schema::dropIfExists('calendars');
    }
};