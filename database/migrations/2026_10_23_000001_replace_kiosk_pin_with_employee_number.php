<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The kiosk is now identified with the employee number, so the hashed
     * kiosk pin (and its lookup fingerprint) is no longer needed.
     */
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->renameColumn('kiosk_pin_fallback_enabled', 'kiosk_employee_number_enabled');
        });

        Schema::table('payroll_profiles', function (Blueprint $table) {
            $table->dropIndex('payroll_profiles_kiosk_pin_lookup_index');
        });

        Schema::table('payroll_profiles', function (Blueprint $table) {
            $table->dropColumn(['kiosk_pin', 'kiosk_pin_lookup']);
        });
    }

    public function down(): void
    {
        Schema::table('payroll_profiles', function (Blueprint $table) {
            $table->string('kiosk_pin')->nullable();
            $table->string('kiosk_pin_lookup', 64)->nullable()->index();
        });

        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->renameColumn('kiosk_employee_number_enabled', 'kiosk_pin_fallback_enabled');
        });
    }
};
