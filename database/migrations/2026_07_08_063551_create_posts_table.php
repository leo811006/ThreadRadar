<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('threads_url', 512)->unique();
            $table->string('author_name');
            $table->string('author_username')->index();
            $table->dateTime('posted_at')->index();
            $table->text('content');
            $table->json('images')->nullable();
            $table->json('videos')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->unsignedBigInteger('replies_count')->default(0);
            $table->unsignedBigInteger('reposts_count')->default(0);
            $table->unsignedBigInteger('quotes_count')->default(0);
            $table->boolean('is_verified_author')->default(false);
            $table->text('ai_summary')->nullable();
            $table->json('ai_tags')->nullable();
            $table->string('ai_sentiment', 50)->nullable();
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->timestamps();

            $table->index(['views_count', 'likes_count']);

            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText('content');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
