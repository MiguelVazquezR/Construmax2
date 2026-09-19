<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Frozen payslips generated when a period is closed, with their concept
     * lines and the daily attendance snapshot used to compute them.
     */
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Snapshot of the collaborator data at close time
            $table->string('employee_number')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->date('hire_date')->nullable();
            $table->decimal('daily_salary', 12, 2)->default(0);
            $table->decimal('daily_hours', 5, 2)->nullable();

            // Totals
            $table->decimal('days_worked', 6, 2)->default(0);
            $table->decimal('days_paid', 6, 2)->default(0);
            $table->decimal('unpaid_days', 6, 2)->default(0);
            $table->integer('late_minutes')->default(0);
            $table->decimal('late_discount', 12, 2)->default(0);
            $table->integer('overtime_double_minutes')->default(0);
            $table->integer('overtime_triple_minutes')->default(0);
            $table->decimal('overtime_amount', 12, 2)->default(0);
            $table->decimal('holiday_days', 6, 2)->default(0);
            $table->decimal('holiday_amount', 12, 2)->default(0);
            $table->decimal('vacation_days', 6, 2)->default(0);
            $table->decimal('incapacity_days', 6, 2)->default(0);
            $table->decimal('incapacity_amount', 12, 2)->default(0);
            $table->decimal('adjustments_earnings', 12, 2)->default(0);
            $table->decimal('adjustments_deductions', 12, 2)->default(0);
            $table->decimal('total_gross', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('total_net', 12, 2)->default(0);

            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'user_id']);
        });

        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->string('concept');
            $table->string('type', 20); // earning | deduction
            $table->decimal('quantity', 8, 2)->nullable();
            $table->decimal('unit_rate', 12, 2)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('source', 20)->default('attendance'); // attendance | overtime | holiday | incapacity | leave | adjustment
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payslip_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 30);
            $table->time('first_in')->nullable();
            $table->time('lunch_start')->nullable();
            $table->time('lunch_end')->nullable();
            $table->time('last_out')->nullable();
            $table->integer('worked_minutes')->default(0);
            $table->integer('late_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_days');
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
    }
};
