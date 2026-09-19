<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payroll periods. The open one is closed automatically by the
     * payroll:close-period command at 01:00 on the next period start day.
     */
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('weekly'); // weekly | biweekly | semimonthly
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open'); // open | closed
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('total_gross', 12, 2)->nullable();
            $table->decimal('total_deductions', 12, 2)->nullable();
            $table->decimal('total_net', 12, 2)->nullable();

            // Mirror expense registered in "Control de gastos" when closed.
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();

            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
