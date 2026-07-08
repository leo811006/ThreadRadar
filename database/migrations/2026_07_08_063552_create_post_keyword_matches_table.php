<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_keyword_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $table->dateTime('matched_at');
            $table->dateTime('notified_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['post_id', 'keyword_id']);
            $table->index(['keyword_id', 'notified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_keyword_matches');
    }
};
