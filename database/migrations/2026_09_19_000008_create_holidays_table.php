<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalog of holidays: the mandatory rest days of the Federal Labor Law
     * (generated automatically) plus any manual company day.
     */
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('year')->index();
            $table->string('source', 20)->default('lft'); // lft | manual
            $table->boolean('is_mandatory')->default(true); // paid rest day
            $table->boolean('apply_extra_pay')->default(true); // extra pay when worked
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
