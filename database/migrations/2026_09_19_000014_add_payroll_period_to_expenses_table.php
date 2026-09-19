<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link between the automatic payroll expense and its period, plus the
     * now nullable created_by (the closing command runs without a user).
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('payroll_period_id')->nullable()->unique()
                ->after('deposit_id')
                ->constrained('payroll_periods')->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payroll_period_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable(false)->change();
        });
    }
};
