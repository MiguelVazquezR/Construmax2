<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deterministic HMAC of the kiosk pin: lets the kiosk find the collaborator
     * from the typed pin alone (the stored pin is bcrypt and cannot be searched).
     */
    public function up(): void
    {
        Schema::table('payroll_profiles', function (Blueprint $table) {
            $table->string('kiosk_pin_lookup', 64)->nullable()->after('kiosk_pin')->index();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_profiles', function (Blueprint $table) {
            $table->dropColumn('kiosk_pin_lookup');
        });
    }
};
