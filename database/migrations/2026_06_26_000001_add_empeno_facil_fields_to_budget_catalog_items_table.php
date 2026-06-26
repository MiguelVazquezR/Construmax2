<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_catalog_items', function (Blueprint $table) {
            $table->string('type')->default('material')->after('budget_catalog_id');
            $table->string('technician')->nullable()->after('unit');
            $table->decimal('hours', 10, 2)->nullable()->after('technician');
            $table->decimal('rate', 12, 2)->nullable()->after('hours');
        });
    }

    public function down(): void
    {
        Schema::table('budget_catalog_items', function (Blueprint $table) {
            $table->dropColumn(['type', 'technician', 'hours', 'rate']);
        });
    }
};
