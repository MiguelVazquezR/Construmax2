<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_concepts', function (Blueprint $table) {
            // Cost type of the concept: 'labor' (mano de obra) or 'material' (materiales).
            // Nullable on purpose: concepts created before the feature have an
            // unknown type and stay uncategorized until edited.
            $table->string('type')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('budget_concepts', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
