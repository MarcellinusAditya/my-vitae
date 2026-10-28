<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_requirement_analysis_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('title');
            $table->string('template_key', 40)->default('ats_plain');
            $table->string('language', 5)->default('id');
            $table->string('tone', 20)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('generation_status', 20)->default('pending');
            $table->string('error_code', 40)->nullable();
            $table->json('content_json')->nullable();
            $table->string('model_name', 120)->nullable();
            $table->timestamps();

            $table->index(['job_application_id', 'type', 'version']);
            $table->index(['generation_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_documents');
    }
};
