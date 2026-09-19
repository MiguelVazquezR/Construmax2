<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attendance incidents: justified/unjustified absences, medical leaves,
     * paid/unpaid permissions and vacations (linked to an approved request).
     */
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // absence_justified | absence_unjustified | medical_leave
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
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
