<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Target role/industry sharpen the AI prompts: without them every generated
 * document is generic. Stored on the profile because that is where the other
 * generation preferences already live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('career_profiles', function (Blueprint $table) {
            $table->string('target_role', 120)->nullable()->after('title');
            $table->string('target_industry', 120)->nullable()->after('target_role');
        });
    }

    public function down(): void
    {
        Schema::table('career_profiles', function (Blueprint $table) {
            $table->dropColumn(['target_role', 'target_industry']);
        });
    }
};