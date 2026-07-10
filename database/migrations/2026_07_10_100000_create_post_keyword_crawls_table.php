<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 記錄「此文章曾被哪個關鍵字巡檢到」，無論是否達標，供列表顯示未達標
        // 文章當初所屬的關鍵字。與 post_keyword_matches（僅達標時寫入、驅動通知）
        // 是兩個獨立語意，不可合併，見該 migration 的用途說明。
        Schema::create('post_keyword_crawls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['post_id', 'keyword_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_keyword_crawls');
    }
};
