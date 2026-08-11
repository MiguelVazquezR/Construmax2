<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_bank_accounts', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('technician_id');
        });
    }

    public function down(): void
    {
        Schema::table('technician_bank_accounts', function (Blueprint $table) {
            $table->dropColumn('bank_name');
        });
    }
};
