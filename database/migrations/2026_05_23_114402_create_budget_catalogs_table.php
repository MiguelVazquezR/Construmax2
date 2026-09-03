<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_catalogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->onDelete('cascade');
            $table->integer('version')->default(1);
            
            // Totales de esta versión
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('non_installation_labor', 12, 2)->default(0);
            $table->decimal('labor_utility', 12, 2)->default(0);
            $table->boolean('needs_special_authorization')->default(false);
            $table->text('transfer_notes')->nullable();
            $table->text('customer_notes')->nullable();
            
            // Estado de aprobación
            $table->string('status')->default('pending_approval');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });

        Schema::create('budget_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_catalog_id')->constrained('budget_catalogs')->onDelete('cascade');
            
            $table->string('type')->default('material');
            $table->string('description');
            $table->string('unit');
            $table->string('technician')->nullable();
            $table->decimal('hours', 10, 2)->nullable();
            $table->decimal('rate', 12, 2)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_catalog_items');
        Schema::dropIfExists('budget_catalogs');
    }
};