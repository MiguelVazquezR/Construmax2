<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->comment('Creator / scheduler');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('color', 20)->default('#409EFF');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_work_schedules');
    }
};
