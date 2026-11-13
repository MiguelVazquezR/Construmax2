<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Browser fingerprint of the device that was authorized. It lets the kiosk
     * recognize a device whose browser storage was cleared, without forcing a
     * new registration.
     */
    public function up(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->string('fingerprint_hash', 64)->nullable()->after('token_hash')->index();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_devices', function (Blueprint $table) {
            $table->dropIndex(['fingerprint_hash']);
            $table->dropColumn('fingerprint_hash');
        });
    }
};
