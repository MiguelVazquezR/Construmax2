<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_payments', function (Blueprint $table) {
            $table->id();
            
            // Vinculamos al Presupuesto (La fuente del dinero)
            $table->foreignId('budget_id')->constrained()->onDelete('cascade');
            
            // Vinculamos al Usuario (Técnico)
            $table->foreignId('user_id')->constrained()->comment('Técnico que recibe el pago');
            
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->string('payment_method')->default('Transferencia');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_payments');
    }
};