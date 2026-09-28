<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Complete payroll and HR module in a single migration.
     *
     * It covers: configuration, collaborator profiles, attendance devices,
     * punches and day overrides, work shifts and their assignments, holidays,
     * vacations (requests, adjustments and service-year periods), incidents,
     * payroll periods, payslips (lines and frozen days), face enrollments,
     * payroll notes and the mirror expense registered when a period closes.
     */
    public function up(): void
    {
        // -----------------------------------------------------------------
        // Configuration (singleton row)
        // -----------------------------------------------------------------
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
            $table->boolean('vacation_premium_notice_enabled')->default(true);
            $table->string('vacation_premium_notice_mode', 20)->default('once'); // once | daily

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

        // -----------------------------------------------------------------
        // Collaborator profiles
        // -----------------------------------------------------------------
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

            // Kiosk fallback identification (hashed pin + searchable HMAC)
            $table->string('kiosk_pin')->nullable();
            $table->string('kiosk_pin_lookup', 64)->nullable()->index();

            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // -----------------------------------------------------------------
        // Attendance: devices, punches and day overrides
        // -----------------------------------------------------------------
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('token_hash')->unique();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();

            // Audit: who registered and authorized the device
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->string('last_user_agent')->nullable();

            $table->boolean('is_active')->default(true);
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();
        });

        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_device_id')->nullable()
                ->constrained('attendance_devices')->nullOnDelete();

            // check_in | lunch_start | lunch_end | break_start | break_end | check_out
            $table->string('type', 20);
            $table->dateTime('punched_at');

            // kiosk | remote | manual
            $table->string('source', 20)->default('kiosk');
            // face | pin | manual
            $table->string('identifier_method', 20)->default('pin');
            $table->decimal('face_similarity', 5, 2)->nullable();

            // Remote attendance location
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('location_accuracy', 8, 2)->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();

            // Audit of manual corrections
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();
            $table->string('edit_reason')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'punched_at']);
        });

        Schema::create('attendance_day_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('late_ignored')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });

        // -----------------------------------------------------------------
        // Work shifts and their assignments
        // -----------------------------------------------------------------
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('fixed'); // fixed | flexible | per_day

            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('meal_minutes')->default(60);
            $table->boolean('is_meal_paid')->default(false);
            $table->boolean('pays_rest_days')->default(false);

            // ISO weekdays (1 = monday ... 7 = sunday)
            $table->json('days');
            // Per-day start/end times for "per_day" shifts (keys are ISO weekdays)
            $table->json('day_schedules')->nullable();

            $table->decimal('required_daily_hours', 5, 2)->nullable(); // flexible shifts
            $table->unsignedSmallInteger('late_tolerance_minutes')->nullable(); // overrides settings
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();

            // Exactly one of these is set: an individual or a department.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('department')->nullable();

            $table->string('type', 20)->default('fixed'); // fixed | rotation
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();

            // Ordered shift ids cycled weekly (rotation assignments only).
            $table->json('rotation')->nullable();

            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date']);
            $table->index(['department', 'start_date']);
        });

        // -----------------------------------------------------------------
        // Holidays and vacations
        // -----------------------------------------------------------------
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('year')->index();
            $table->string('source', 20)->default('lft'); // lft | manual
            $table->boolean('is_mandatory')->default(true); // paid rest day
            $table->boolean('apply_extra_pay')->default(true); // extra pay when worked
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('vacation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 6, 2);
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | cancelled
            $table->string('reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date']);
        });

        Schema::create('vacation_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // initial | grant | taken | adjustment
            $table->decimal('days', 5, 2); // negative values discount days
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('vacation_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year_number');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('entitled_days', 5, 2)->default(0);
            $table->decimal('accrued_days', 6, 2)->default(0);
            $table->decimal('taken_days', 6, 2)->default(0);
            $table->boolean('is_customized')->default(false);
            $table->date('premium_paid_at')->nullable();
            $table->timestamp('premium_notified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_id', 'year_number']);
        });

        // -----------------------------------------------------------------
        // Incidents (absences, medical leaves, permissions, vacations)
        // -----------------------------------------------------------------
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // absence_justified | absence_unjustified | medical_leave | work_incapacity
            // | permission_paid | permission_unpaid | vacation | other
            $table->string('type', 30);

            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('days', 6, 2)->nullable();

            // null = use the default pay rule of the type
            $table->boolean('is_paid')->nullable();
            $table->string('status', 20)->default('approved'); // approved | cancelled

            $table->foreignId('vacation_request_id')->nullable()
                ->constrained('vacation_requests')->nullOnDelete();

            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date']);
        });

        // -----------------------------------------------------------------
        // Payroll periods, manual adjustments and frozen payslips
        // -----------------------------------------------------------------
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('weekly'); // weekly | biweekly | semimonthly
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open'); // open | closed
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
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

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // earning | deduction
            $table->string('concept');
            $table->decimal('amount', 12, 2);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

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
            $table->string('source', 20)->default('attendance'); // attendance | overtime | holiday | rest_day | incapacity | leave | adjustment
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

        // -----------------------------------------------------------------
        // Face enrollments (AWS Rekognition references) and period notes
        // -----------------------------------------------------------------
        Schema::create('face_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('collection_id');
            $table->string('face_id');
            $table->string('external_image_id');
            $table->string('status', 20)->default('active'); // active | removed | failed
            $table->decimal('quality', 8, 2)->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('enrolled_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('payroll_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payroll_period_id', 'user_id']);
        });

        // -----------------------------------------------------------------
        // Expenses: link with the mirror payroll period expense
        // -----------------------------------------------------------------
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('payroll_period_id')->nullable()->unique()
                ->after('deposit_id')
                ->constrained('payroll_periods')->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_period_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable(false)->change();
        });

        Schema::dropIfExists('payroll_notes');
        Schema::dropIfExists('face_enrollments');
        Schema::dropIfExists('payslip_days');
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('vacation_periods');
        Schema::dropIfExists('vacation_adjustments');
        Schema::dropIfExists('vacation_requests');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('attendance_day_overrides');
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('attendance_devices');
        Schema::dropIfExists('payroll_profiles');

        // The "Nómina" expense category is intentionally kept: expenses may
        // still reference it after the payroll module is rolled back.
        Schema::dropIfExists('payroll_settings');
    }
};
