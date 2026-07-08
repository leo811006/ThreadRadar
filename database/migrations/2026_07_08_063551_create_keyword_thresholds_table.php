<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('group')->default(0);
            $table->enum('metric', ['views', 'likes', 'replies', 'reposts', 'quotes']);
            $table->enum('operator', ['>', '>=', '=', '<', '<=']);
            $table->unsignedBigInteger('value');
            $table->timestamps();

            $table->index('keyword_id');
            $table->index(['keyword_id', 'group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_thresholds');
    }
};
