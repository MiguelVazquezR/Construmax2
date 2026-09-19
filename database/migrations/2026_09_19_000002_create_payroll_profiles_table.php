<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payroll and attendance profile for any user subject to the HR module:
     * employees (payroll + attendance) and technicians (attendance).
     */
    public function up(): void
    {
        Schema::create('payroll_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('employee_number')->nullable()->unique();
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();

            // Payroll data (daily salary basis; default daily hours live in settings)
            $table->decimal('daily_salary', 12, 2)->nullable();
            $table->decimal('daily_hours', 5, 2)->nullable();

            // Module toggles
            $table->boolean('is_payroll_subject')->default(false);
            $table->boolean('is_attendance_subject')->default(false);
            $table->boolean('can_remote_attendance')->default(false);

            // Kiosk fallback identification (hashed)
            $table->string('kiosk_pin')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_profiles');
    }
};
