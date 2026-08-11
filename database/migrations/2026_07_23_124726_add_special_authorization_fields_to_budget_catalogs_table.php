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
            $table->boolean('needs_special_authorization')->default(false)->after('labor_utility');
            $table->text('transfer_notes')->nullable()->after('needs_special_authorization');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_catalogs', function (Blueprint $table) {
            $table->dropColumn('transfer_notes');
            $table->dropColumn('needs_special_authorization');
        });
    }
};