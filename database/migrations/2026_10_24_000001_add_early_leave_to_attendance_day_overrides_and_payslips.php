<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow leaving before the scheduled exit time without a discount: the
     * day override ignores the early leave, just like `late_ignored` does for
     * late arrivals. Payslips freeze the early leave minutes and amount.
     */
    public function up(): void
    {
        Schema::table('attendance_day_overrides', function (Blueprint $table) {
            $table->boolean('early_leave_ignored')->default(false)->after('late_ignored');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->integer('early_leave_minutes')->default(0)->after('late_discount');
            $table->decimal('early_leave_discount', 12, 2)->default(0)->after('early_leave_minutes');
        });

        Schema::table('payslip_days', function (Blueprint $table) {
            $table->integer('early_leave_minutes')->default(0)->after('late_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_day_overrides', function (Blueprint $table) {
            $table->dropColumn('early_leave_ignored');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['early_leave_minutes', 'early_leave_discount']);
        });

        Schema::table('payslip_days', function (Blueprint $table) {
            $table->dropColumn('early_leave_minutes');
        });
    }
};
