<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla Principal: Presupuestos
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('service_type');
            $table->string('status')->default('Presupuesto enviado');
            
            $table->text('description')->nullable();
            $table->string('duration')->nullable();
            $table->string('priority')->default('Media');
            
            // --- NUEVOS CAMPOS DE MONEDA ---
            $table->string('currency', 3)->default('MXN'); // MXN, USD
            $table->decimal('exchange_rate', 10, 4)->default(1); // Valor del dólar si aplica
            
            // Relaciones
            $table->foreignId('user_id')->constrained()->comment('Responsable'); 
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_contact_id')->constrained()->onDelete('cascade');
            
            $table->string('branch')->nullable();
            
            $table->timestamps();
        });

        // Tabla: Conceptos
        Schema::create('budget_concepts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->onDelete('cascade');
            $table->string('concept');
            $table->decimal('amount', 12, 2); // Aumenté precisión por si son montos grandes
            $table->timestamps();
        });

        // Tabla: Pagos
        Schema::create('budget_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->onDelete('cascade');
            $table->decimal('amount', 12, 2);
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