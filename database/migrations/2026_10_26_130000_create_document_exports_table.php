<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_document_id')->constrained()->cascadeOnDelete();
            $table->string('format', 10);
            $table->string('filename', 255);
            $table->string('storage_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('status', 20)->default('pending');
            $table->string('error_code', 40)->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['generated_document_id', 'format', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_exports');
    }
};
