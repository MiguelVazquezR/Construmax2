<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla Principal: Presupuestos (Antes Servicios)
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nombre del proyecto
            $table->string('service_type'); // Tipo de servicio (Iluminación, Herrería, etc.)
            
            // Status actualizados para el flujo administrativo
            $table->string('status')->default('Presupuesto enviado'); 
            // Posibles: Presupuesto enviado, Facturado, Trabajo en proceso, Trabajo terminado, Pagado, Perdido
            
            $table->text('description')->nullable();
            $table->string('duration')->nullable(); 
            $table->string('priority')->default('Media'); 
            
            // Relaciones
            $table->foreignId('user_id')->constrained()->comment('Responsable'); 
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_contact_id')->constrained()->onDelete('cascade');
            
            $table->string('branch')->nullable(); // Sucursal
            
            $table->timestamps();
        });

        // Tabla: Conceptos del presupuesto (Costos)
        Schema::create('budget_concepts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->onDelete('cascade');
            $table->string('concept');
            $table->decimal('amount', 10, 2); // Monto
            $table->timestamps();
        });

        // Tabla: Pagos asociados al presupuesto/proyecto
        Schema::create('budget_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->string('reference')->nullable(); 
            $table->string('payment_method')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_payments');
        Schema::dropIfExists('budget_concepts');
        Schema::dropIfExists('budgets');
    }
};