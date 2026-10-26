<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('career_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('extraction_status', 20)->default('pending');
            $table->string('extraction_error_code', 40)->nullable();
            $table->json('draft')->nullable();
            $table->string('model_name', 120)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'extraction_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_documents');
    }
};
