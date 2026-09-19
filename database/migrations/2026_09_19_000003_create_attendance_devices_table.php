<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Authorized physical devices (tablets/screens) allowed to open the
     * attendance kiosk. The access token is stored hashed; the plain value
     * lives only in the device browser (localStorage).
     */
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_devices');
    }
};
