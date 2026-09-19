<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Face enrollments: references (FaceId) stored in the AWS Rekognition
     * collection. The reference images are not kept locally.
     */
    public function up(): void
    {
        Schema::create('face_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('collection_id');
            $table->string('face_id');
            $table->string('external_image_id');
            $table->string('status', 20)->default('active'); // active | removed | failed
            $table->decimal('quality', 8, 2)->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('enrolled_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_enrollments');
    }
};
