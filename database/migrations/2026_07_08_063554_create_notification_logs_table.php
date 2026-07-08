<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_keyword_match_id')->constrained()->cascadeOnDelete();
            $table->enum('channel_type', ['email', 'discord', 'slack', 'line', 'telegram', 'webhook']);
            $table->enum('status', ['sent', 'failed']);
            $table->json('payload');
            $table->text('error_message')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('post_keyword_match_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
