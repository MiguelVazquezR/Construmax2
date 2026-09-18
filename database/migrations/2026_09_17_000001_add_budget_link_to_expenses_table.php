<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Expenses can belong to a budget (budget expenses) or stand alone (general expenses)
            $table->foreignId('budget_id')->nullable()->after('ticket_id')
                ->constrained('budgets')->nullOnDelete();

            // Breakdown concept this expense pays for (one expense per concept)
            $table->foreignId('budget_concept_id')->nullable()->unique()->after('budget_id')
                ->constrained('budget_concepts')->nullOnDelete();

            // Commission paid on top of the budget breakdown
            $table->boolean('is_commission')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('budget_concept_id');
            $table->dropConstrainedForeignId('budget_id');
            $table->dropColumn('is_commission');
        });
    }
};
