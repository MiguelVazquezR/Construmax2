<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Raw attendance punches (entry, meal, intermediate permits and exit).
     * Corrections by an administrator are audited in the edited_* columns.
     */
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
