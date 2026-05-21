<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla Principal: Tickets de Servicio (Entidad Central)
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            
            // --- DATOS DEL CLIENTE Y UBICACIÓN ---
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_contact_id')->constrained()->onDelete('cascade');
            $table->string('branch')->nullable(); // Sucursal
            
            // --- DATOS DEL PROYECTO ---
            $table->string('name'); // Nombre del proyecto o necesidad
            $table->string('service_type'); // Tipo de servicio
            $table->string('duration')->nullable(); // Duración estimada general
            
            // CAMBIO: Se elimina user_id (Encargado) y se agrega technicians como JSON
            $table->json('technicians')->nullable()->comment('Arreglo de IDs de técnicos ejecutores');
            
            $table->string('status')->default('Borrador'); 
            // Status: Borrador, Levantamiento, Catálogo, Proceso de ejecución, Ejecutado, Facturado, Pagado
            
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