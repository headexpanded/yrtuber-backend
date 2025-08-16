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
        Schema::create('collection_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade'); // Who shared it
            $table->string('platform'); // 'twitter', 'facebook', 'email', 'link'
            $table->string('url'); // The actual share URL
            $table->enum('share_type', ['public', 'private', 'temporary'])->default('public');
            $table->timestamp('shared_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable(); // Store platform-specific data
            $table->json('analytics')->nullable(); // Store analytics data
            $table->timestamps();

            $table->index(['collection_id', 'created_at']);
            $table->index(['platform', 'created_at']);
            $table->index(['share_type', 'created_at']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_shares');
    }
};
