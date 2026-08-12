<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('budget_catalogs', function (Blueprint $table) {
            $table->text('customer_notes')->nullable()->after('labor_utility');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_catalogs', function (Blueprint $table) {
            $table->dropColumn('customer_notes');
        });
    }
};