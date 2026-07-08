<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_notification_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $table->enum('channel_type', ['email', 'discord', 'slack', 'line', 'telegram', 'webhook']);
            $table->json('config');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('keyword_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_notification_channels');
    }
};
