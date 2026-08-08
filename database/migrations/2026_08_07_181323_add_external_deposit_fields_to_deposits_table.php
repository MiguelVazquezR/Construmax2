<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            // Mark a deposit as external (no technician, ticket, or budget)
            $table->boolean('is_external')->default(false)->after('deposit_type_id');

            // Make ticket-related FKs nullable for external deposits
            $table->foreignId('technician_id')->nullable()->change();
            $table->foreignId('technician_bank_account_id')->nullable()->change();
            $table->foreignId('ticket_id')->nullable()->change();
            $table->foreignId('budget_id')->nullable()->change();

            // External bank details (manually entered, no linked technician account)
            $table->string('external_bank_name')->nullable()->after('is_external');
            $table->string('external_beneficiary_name')->nullable()->after('external_bank_name');
            $table->string('external_account_number')->nullable()->after('external_beneficiary_name');
            $table->string('external_clabe')->nullable()->after('external_account_number');
            $table->string('external_card_number')->nullable()->after('external_clabe');
            $table->string('external_branch_number')->nullable()->after('external_card_number');
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn([
                'is_external',
                'external_bank_name',
                'external_beneficiary_name',
                'external_account_number',
                'external_clabe',
                'external_card_number',
                'external_branch_number',
            ]);

            // Restore NOT NULL constraints
            $table->foreignId('technician_id')->nullable(false)->change();
            $table->foreignId('technician_bank_account_id')->nullable(false)->change();
            $table->foreignId('ticket_id')->nullable(false)->change();
            $table->foreignId('budget_id')->nullable(false)->change();
        });
    }
};