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
        Schema::create('collection_video', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->onDelete('cascade');
            $table->foreignId('video_id')->constrained()->onDelete('cascade');
            $table->text('curator_notes')->nullable(); // Curator's commentary on the video
            $table->integer('position')->default(0); // Order within the collection
            $table->timestamp('added_at')->useCurrent();
            $table->timestamps();

            $table->unique(['collection_id', 'video_id']);
            $table->index(['collection_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_video');
    }
};
