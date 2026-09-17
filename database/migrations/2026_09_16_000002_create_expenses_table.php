<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->string('folio')->unique();

            $table->foreignId('expense_category_id')->nullable()
                ->constrained('expense_categories')->nullOnDelete();

            // Optional link: expenses can belong to a project (ticket) or be general expenses
            $table->foreignId('ticket_id')->nullable()
                ->constrained('tickets')->nullOnDelete();

            $table->string('concept');
            $table->string('reference')->nullable(); // Receipt / invoice number
            $table->text('notes')->nullable();

            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('payment_method')->nullable(); // cash | transfer | card | check | other
            $table->string('status')->default('pending'); // pending | paid | cancelled

            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('expense_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
