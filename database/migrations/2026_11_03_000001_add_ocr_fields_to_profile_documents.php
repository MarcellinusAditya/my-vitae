<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OCR support: record whether a scanned CV was read by the local OCR pass and
 * which document language was used, so re-runs pick the right Tesseract pack.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_documents', function (Blueprint $table) {
            $table->boolean('ocr_used')->default(false)->after('extraction_error_code');
            $table->string('language', 5)->nullable()->after('ocr_used');
        });
    }

    public function down(): void
    {
        Schema::table('profile_documents', function (Blueprint $table) {
            $table->dropColumn(['ocr_used', 'language']);
        });
    }
};