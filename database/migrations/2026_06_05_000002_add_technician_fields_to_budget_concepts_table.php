<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_concepts', function (Blueprint $table) {
            $table->boolean('paid_to_technician')->default(false)->after('amount');
            $table->date('payment_date')->nullable()->after('paid_to_technician');
        });
    }

    public function down(): void
    {
        Schema::table('budget_concepts', function (Blueprint $table) {
            $table->dropColumn(['paid_to_technician', 'payment_date']);
        });
    }
};
