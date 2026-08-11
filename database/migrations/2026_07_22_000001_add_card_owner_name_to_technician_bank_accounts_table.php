<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_bank_accounts', function (Blueprint $table) {
            $table->string('card_owner_name')->nullable()->after('bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('technician_bank_accounts', function (Blueprint $table) {
            $table->dropColumn('card_owner_name');
        });
    }
};
