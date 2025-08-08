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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('username')->unique();
            $table->text('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->string('website')->nullable();
            $table->string('location')->nullable();
            $table->json('social_links')->nullable(); // Store social media links
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_featured_curator')->default(false);
            $table->integer('follower_count')->default(0);
            $table->integer('following_count')->default(0);
            $table->integer('collection_count')->default(0);
            $table->timestamps();

            $table->index(['username']);
            $table->index(['is_verified', 'is_featured_curator']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
