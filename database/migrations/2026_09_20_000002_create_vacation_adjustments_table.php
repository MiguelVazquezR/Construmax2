<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual movements of the vacation balance: the initial balance granted
     * when a collaborator enters the system, extra days granted by the company
     * and positive/negative corrections made by the payroll team.
     */
    public function up(): void
    {
        Schema::create('vacation_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // initial | grant | adjustment
            $table->decimal('days', 5, 2); // negative values discount days
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacation_adjustments');
    }
};
