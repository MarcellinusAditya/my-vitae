<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_documents', function (Blueprint $table) {
            $table->json('section_order')->nullable()->after('content_json');
            $table->unsignedInteger('word_count')->default(0)->after('section_order');
            $table->timestamp('last_saved_at')->nullable()->after('word_count');
            $table->foreignId('parent_version_id')->nullable()->after('job_requirement_analysis_id')
                ->constrained('generated_documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('generated_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_version_id');
            $table->dropColumn(['section_order', 'word_count', 'last_saved_at']);
        });
    }
};
