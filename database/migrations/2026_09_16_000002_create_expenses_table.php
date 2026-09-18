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

            // Expenses can belong to a budget (budget expenses) or stand alone (general expenses)
            $table->foreignId('budget_id')->nullable()
                ->constrained('budgets')->nullOnDelete();

            // Breakdown concept this expense pays for (one expense per concept)
            $table->foreignId('budget_concept_id')->nullable()->unique()
                ->constrained('budget_concepts')->nullOnDelete();

            // Deposit mirrored in the expenses module (one expense per deposit)
            $table->foreignId('deposit_id')->nullable()->unique()
                ->constrained('deposits')->nullOnDelete();

            $table->string('concept');
            $table->string('reference')->nullable(); // Receipt / invoice number
            $table->text('notes')->nullable();

            $table->decimal('amount', 12, 2);

            // Fee charged by the payment channel (e.g. OXXO) on top of the expense amount
            $table->decimal('commission_amount', 12, 2)->nullable();

            $table->date('expense_date');
            $table->string('payment_method')->nullable(); // cash | transfer | card | check | other
            $table->string('status')->default('pending'); // pending | paid | cancelled

            // Commission registered as its own expense (batch concept payments)
            $table->boolean('is_commission')->default(false);

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
