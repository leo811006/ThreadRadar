<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('ai_analysis_failed_at')->nullable()->after('ai_sentiment');
            $table->index('ai_analysis_failed_at');
            // text 而非 string：Gemini 例外訊息（如 JSON 解析錯誤）長度不可控，
            // varchar(255) 在 MySQL strict mode 下可能因過長而寫入失敗。
            $table->text('ai_analysis_failure_reason')->nullable()->after('ai_analysis_failed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['ai_analysis_failed_at']);
            $table->dropColumn(['ai_analysis_failed_at', 'ai_analysis_failure_reason']);
        });
    }
};
