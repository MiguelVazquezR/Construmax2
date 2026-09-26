<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vacation periods by service year (anniversary to anniversary). The system
     * creates and refreshes them from the collaborator's hire date; the payroll
     * team can customize the days, register the vacation premium payment and
     * remove wrong periods (deleted rows are never regenerated).
     */
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('vacation_periods');
    }
};
