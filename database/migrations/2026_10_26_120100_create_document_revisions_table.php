<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_document_id')->constrained()->cascadeOnDelete();
            $table->json('content_json');
            $table->json('section_order')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->string('source', 20)->default('manual');
            $table->timestamps();

            $table->index(['generated_document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_revisions');
    }
};
