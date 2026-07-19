<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_acceptance_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')->unique()->constrained('tickets')->cascadeOnDelete();

            // --- Client-side data (auto-populated from DB) ---
            $table->date('report_date');

            // --- Technician-entered fields (from PublicTask.vue) ---
            $table->text('work_description')->nullable();
            $table->dateTime('on_site_start')->nullable();
            $table->dateTime('on_site_end')->nullable();
            $table->text('technician_comments')->nullable();

            // --- Client-entered fields (from signature flow) ---
            $table->text('client_comments')->nullable();
            $table->string('manager_name')->nullable();

            // --- Signature data ---
            $table->longText('signature_data')->nullable();       // base64 PNG from canvas
            $table->string('signatory_name')->nullable();         // name typed at signing
            $table->timestamp('signed_at')->nullable();           // when the signature was captured

            // --- Locking ---
            $table->boolean('is_signed')->default(false);

            // --- Audit ---
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_acceptance_reports');
    }
};
