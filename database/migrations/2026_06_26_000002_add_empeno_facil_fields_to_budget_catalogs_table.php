<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_catalogs', function (Blueprint $table) {
            $table->decimal('non_installation_labor', 12, 2)->default(0)->after('total');
            $table->decimal('labor_utility', 12, 2)->default(0)->after('non_installation_labor');
        });
    }

    public function down(): void
    {
        Schema::table('budget_catalogs', function (Blueprint $table) {
            $table->dropColumn(['non_installation_labor', 'labor_utility']);
        });
    }
};
