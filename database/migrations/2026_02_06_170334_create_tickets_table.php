<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla Principal: Tickets de Servicio
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            
            // Relación con el Presupuesto (Fuente de la verdad comercial)
            $table->foreignId('budget_id')->constrained()->onDelete('cascade');
            
            // Responsable Operativo (Puede ser diferente al vendedor del presupuesto)
            $table->foreignId('user_id')->constrained()->comment('Responsable Técnico');
            
            $table->string('status')->default('Programado'); 
            // Status: Programado, En proceso, En espera, Revisión, Completado, Cancelado
            
            $table->string('priority')->default('Media');
            
            // Fechas del servicio
            $table->date('scheduled_start')->nullable(); // Fecha inicio programada
            $table->date('scheduled_end')->nullable();   // Fecha fin estimada
            
            $table->text('instructions')->nullable(); // Instrucciones operativas específicas
            
            $table->timestamps();
        });

        // Tabla: Tareas / Actividades del Cronograma
        Schema::create('ticket_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->onDelete('cascade');
            
            // Tarea asignada a un técnico específico (opcional)
            $table->foreignId('user_id')->nullable()->constrained()->comment('Técnico asignado');
            
            $table->string('name'); // Nombre de la actividad
            $table->text('description')->nullable();
            
            $table->string('status')->default('Pendiente'); // Pendiente, En proceso, Completada

            $table->dateTime('start_date')->nullable(); // Fecha/Hora de inicio real
            $table->dateTime('due_date')->nullable(); // Fecha/Hora límite para esta tarea
            $table->dateTime('completed_at')->nullable(); // Fecha real de término
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_tasks');
        Schema::dropIfExists('tickets');
    }
};