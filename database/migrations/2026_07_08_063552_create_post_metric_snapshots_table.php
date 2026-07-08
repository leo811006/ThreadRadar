<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('views_count');
            $table->unsignedBigInteger('likes_count');
            $table->unsignedBigInteger('replies_count');
            $table->unsignedBigInteger('reposts_count');
            $table->unsignedBigInteger('quotes_count');
            $table->dateTime('recorded_at');

            $table->index(['post_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_metric_snapshots');
    }
};
