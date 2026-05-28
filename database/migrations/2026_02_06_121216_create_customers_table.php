<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla de Clientes
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');             
            $table->string('business_name');    
            $table->string('rfc', 20);          
            $table->string('payment_condition');
            $table->string('payment_method');   
            $table->string('invoice_usage');    
            $table->string('currency', 3);      
            $table->unsignedSmallInteger('payment_days')->nullable(); 
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Tabla de Sucursales (Entidad propia relacionada al Cliente)
        Schema::create('customer_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('country', 100);
            $table->string('region', 100);
            $table->string('unit', 255);
            $table->string('branch_name', 255);
            $table->timestamps();
        });

        // Tabla de Contactos del Cliente
        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->string('position'); 
            $table->timestamps();
        });

        // Tabla Pivote: Relación Muchos a Muchos entre Contactos y Sucursales
        Schema::create('customer_branch_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_branch_id')->constrained('customer_branches')->onDelete('cascade');
            $table->foreignId('customer_contact_id')->constrained('customer_contacts')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_branch_contact');
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customer_branches');
        Schema::dropIfExists('customers');
    }
};