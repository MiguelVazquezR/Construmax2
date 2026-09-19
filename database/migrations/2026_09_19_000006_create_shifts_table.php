<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Work shifts: fixed schedules (start/end times + working days) and
     * flexible ones (a required number of daily hours, no fixed times).
     */
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('fixed'); // fixed | flexible

            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('meal_minutes')->default(60);
            $table->boolean('is_meal_paid')->default(false);

            // ISO weekdays (1 = monday ... 7 = sunday)
            $table->json('days');

            $table->decimal('required_daily_hours', 5, 2)->nullable(); // flexible shifts
            $table->unsignedSmallInteger('late_tolerance_minutes')->nullable(); // overrides settings
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
