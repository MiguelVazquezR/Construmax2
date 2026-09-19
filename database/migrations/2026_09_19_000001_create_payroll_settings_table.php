<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Singleton row with the payroll module configuration: attendance rules,
     * payroll periods, overtime/late policies, vacations and face recognition.
     */
    public function up(): void
    {
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();

            // Face recognition (AWS Rekognition)
            $table->boolean('face_recognition_enabled')->default(false);
            $table->unsignedSmallInteger('face_match_threshold')->default(90);
            $table->boolean('kiosk_pin_fallback_enabled')->default(true);
            $table->string('rekognition_collection_id')->default('construmax-attendance');

            // Payroll period
            $table->string('period_type', 20)->default('weekly'); // weekly | biweekly | semimonthly
            $table->date('period_anchor_date')->nullable();

            // Late arrivals
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(10);
            $table->string('late_discount_mode', 20)->default('track_only'); // track_only | deduct_minutes

            // Overtime (LFT defaults: double up to 9 hours a week, triple beyond)
            $table->decimal('overtime_double_multiplier', 4, 2)->default(2.00);
            $table->decimal('overtime_triple_multiplier', 4, 2)->default(3.00);
            $table->decimal('overtime_weekly_threshold_hours', 5, 2)->default(9.00);

            // Worked holidays (LFT art. 75: double salary on top of the day)
            $table->decimal('holiday_worked_extra_multiplier', 4, 2)->default(2.00);

            // Vacations
            $table->decimal('vacation_min_days_to_request', 5, 2)->default(1.00);
            $table->unsignedSmallInteger('vacation_carryover_months')->default(18);

            // Medical leaves
            $table->boolean('incapacity_paid')->default(false);
            $table->unsignedSmallInteger('incapacity_pay_percentage')->default(60);

            // Defaults
            $table->decimal('default_daily_hours', 5, 2)->default(8.00);
            $table->foreignId('payroll_expense_category_id')->nullable()
                ->constrained('expense_categories')->nullOnDelete();

            // Attendance evidence
            $table->unsignedSmallInteger('attendance_capture_retention_months')->default(12);
            $table->boolean('remote_geolocation_required')->default(true);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Default expense category for the automatic payroll period expense.
        $categoryId = DB::table('expense_categories')->where('name', 'Nómina')->value('id');

        if (! $categoryId) {
            $categoryId = DB::table('expense_categories')->insertGetId([
                'name' => 'Nómina',
                'is_active' => true,
                'is_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('payroll_settings')->insert([
            'payroll_expense_category_id' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The "Nómina" expense category is intentionally kept: expenses may
        // still reference it after the payroll module is rolled back.
        Schema::dropIfExists('payroll_settings');
    }
};
