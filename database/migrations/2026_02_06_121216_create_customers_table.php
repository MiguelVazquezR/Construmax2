<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabla de Clientes
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');             // Nombre comercial
            $table->string('business_name');    // Razón social
            $table->string('rfc', 20);          // RFC
            $table->string('payment_condition');// Crédito, Contado, Otro
            $table->string('payment_method');   // Transferencia, Efectivo, Otro
            $table->string('invoice_usage');    // Gastos en general, Otro
            $table->string('currency', 3);      // MXN, USD
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Tabla de Contactos del Cliente
        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->string('position'); // Puesto
            $table->text('branches'); // Sucursales (Texto libre o separado por comas)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customers');
    }
};