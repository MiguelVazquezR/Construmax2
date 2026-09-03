<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('technician_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('technician_bank_account_id')->nullable()->constrained('technician_bank_accounts');
            $table->foreignId('ticket_id')->nullable()->constrained();
            $table->foreignId('budget_id')->nullable()->constrained();
            $table->foreignId('deposit_type_id')->constrained('deposit_types');

            // Mark a deposit as external (no technician, ticket, or budget)
            $table->boolean('is_external')->default(false);

            // External bank details (manually entered, no linked technician account)
            $table->string('external_bank_name')->nullable();
            $table->string('external_beneficiary_name')->nullable();
            $table->string('external_account_number')->nullable();
            $table->string('external_clabe')->nullable();
            $table->string('external_card_number')->nullable();
            $table->string('external_branch_number')->nullable();

            $table->decimal('amount', 10, 2);
            $table->enum('shift', ['matutino', 'vespertino']);
            $table->date('scheduled_date');
            $table->enum('status', ['pending', 'approved', 'completed'])->default('pending');

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->decimal('commission_amount', 10, 2)->nullable();

            $table->foreignId('technician_payment_id')->nullable()->constrained('technician_payments')->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};