<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vacation premium notices: the premium is paid when the collaborator
     * completes each year of service. "once" notifies at the start of the
     * payroll period that contains the anniversary; "daily" repeats the notice
     * every day of that period until the payment is registered.
     */
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->boolean('vacation_premium_notice_enabled')->default(true)->after('vacation_carryover_months');
            $table->string('vacation_premium_notice_mode', 20)->default('once')->after('vacation_premium_notice_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn(['vacation_premium_notice_enabled', 'vacation_premium_notice_mode']);
        });
    }
};
