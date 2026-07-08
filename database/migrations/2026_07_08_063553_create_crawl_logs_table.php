<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawl_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['success', 'failed', 'quota_exceeded']);
            $table->unsignedInteger('posts_found')->default(0);
            $table->unsignedInteger('posts_created')->default(0);
            $table->unsignedInteger('posts_updated')->default(0);
            $table->unsignedInteger('api_calls_used')->default(0);
            $table->text('error_message')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();

            $table->index('keyword_id');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_logs');
    }
};
