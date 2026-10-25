<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('status_changed_at')->nullable()->after('status');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->timestamp('status_changed_at')->nullable()->after('status');
        });

        // Existing rows have no status history: the last update is the closest known date.
        DB::table('tickets')->whereNull('status_changed_at')->update([
            'status_changed_at' => DB::raw('COALESCE(updated_at, CURRENT_TIMESTAMP)'),
        ]);

        DB::table('budgets')->whereNull('status_changed_at')->update([
            'status_changed_at' => DB::raw('COALESCE(updated_at, CURRENT_TIMESTAMP)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('status_changed_at');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropColumn('status_changed_at');
        });
    }
};
