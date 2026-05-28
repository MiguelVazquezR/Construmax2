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
            
            $table->timestamps();
        });

        Schema::create('budget_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_catalog_id')->constrained('budget_catalogs')->onDelete('cascade');
            
            $table->string('description');
            $table->string('unit');
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