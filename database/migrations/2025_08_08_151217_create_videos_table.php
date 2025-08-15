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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('youtube_id')->unique(); // YouTube video ID
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('channel_name')->nullable();
            $table->string('channel_id')->nullable();
            $table->integer('duration')->nullable(); // Duration in seconds
            $table->timestamp('published_at')->nullable();
            $table->integer('view_count')->default(0);
            $table->integer('like_count')->default(0);
            $table->json('metadata')->nullable(); // Store additional YouTube metadata
            $table->timestamps();

            $table->index(['youtube_id']);
            $table->index(['channel_id']);
            $table->index(['published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
