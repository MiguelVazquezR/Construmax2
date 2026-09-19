<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shift assignments for a collaborator or a whole department. Fixed
     * assignments point to a single shift; rotation assignments cycle through
     * a list of shifts week by week.
     */
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
    }
};
