<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_tasks', function (Blueprint $table) {
            $table->text('technician_notes')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_tasks', function (Blueprint $table) {
            $table->dropColumn('technician_notes');
        });
    }
};
