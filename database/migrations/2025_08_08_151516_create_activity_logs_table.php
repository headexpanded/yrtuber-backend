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
                Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('action'); // e.g., 'collection.created', 'video.added'
            $table->morphs('subject'); // The object being acted upon
            $table->foreignId('target_user_id')->nullable()->constrained('users')->onDelete('cascade'); // User being targeted by the action
            $table->json('properties')->nullable(); // Additional data about the action
            $table->enum('visibility', ['public', 'private', 'followers'])->default('public');
            $table->integer('aggregated_count')->default(1); // For aggregating similar actions
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['target_user_id', 'created_at']);
            $table->index(['visibility', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
