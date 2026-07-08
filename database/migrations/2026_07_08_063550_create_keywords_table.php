<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('crawl_interval_min');
            $table->enum('time_range_type', ['30min', '1h', '6h', '24h', '7d', 'custom']);
            $table->dateTime('time_range_custom_from')->nullable();
            $table->dateTime('time_range_custom_to')->nullable();
            $table->dateTime('last_crawled_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'last_crawled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keywords');
    }
};
