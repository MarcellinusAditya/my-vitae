<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_requirement_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('error_code', 40)->nullable();
            $table->json('analysis_json')->nullable();
            $table->json('profile_snapshot')->nullable();
            $table->json('job_snapshot')->nullable();
            $table->string('model_name', 120)->nullable();
            $table->timestamps();

            $table->index(['job_application_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_requirement_analyses');
    }
};
