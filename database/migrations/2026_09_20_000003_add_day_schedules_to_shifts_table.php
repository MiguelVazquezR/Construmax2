<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-day schedules for "por día" shifts: each key is an ISO weekday
     * (1 = monday ... 7 = sunday) with its own start/end time and meal
     * minutes. Days missing from the object are rest days.
     */
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->json('day_schedules')->nullable()->after('days');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('day_schedules');
        });
    }
};
