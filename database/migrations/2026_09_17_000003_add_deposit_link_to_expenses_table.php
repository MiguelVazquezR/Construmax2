<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Deposit mirrored in the expenses module (one expense per deposit)
            $table->foreignId('deposit_id')->nullable()->unique()->after('budget_concept_id')
                ->constrained('deposits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deposit_id');
        });
    }
};
