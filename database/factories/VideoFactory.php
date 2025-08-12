<?php

namespace Database\Factories;

use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Video>
 */
class VideoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $youtubeId = fake()->regexify('[A-Za-z0-9]{11}'); // YouTube video IDs are 11 characters

        return [
            'youtube_id' => $youtubeId,
            'title' => fake()->sentence(4, 8),
            'description' => fake()->paragraph(3),
            'thumbnail_url' => "https://img.youtube.com/vi/{$youtubeId}/maxresdefault.jpg",
            'channel_name' => fake()->company(),
            'channel_id' => fake()->regexify('[A-Za-z0-9]{24}'), // YouTube channel IDs are 24 characters
            'duration' => fake()->numberBetween(60, 3600), // 1 minute to 1 hour in seconds
            'published_at' => fake()->dateTimeBetween('-2 years', 'now'),
            'view_count' => fake()->numberBetween(100, 10000000),
            'like_count' => fake()->numberBetween(10, 1000000),
            'metadata' => [
                'category' => fake()->randomElement(['Education', 'Entertainment', 'Music', 'Gaming', 'Technology']),
                'tags' => fake()->words(5),
                'language' => fake()->randomElement(['en', 'es', 'fr', 'de', 'ja']),
                'quality' => fake()->randomElement(['1080p', '720p', '480p']),
            ],
        ];
    }

    /**
     * Indicate that the video is short (under 5 minutes).
     */
    public function short(): static
    {
        return $this->state(fn (array $attributes) => [
            'duration' => fake()->numberBetween(60, 300),
        ]);
    }

    /**
     * Indicate that the video is long (over 20 minutes).
     */
    public function long(): static
    {
        return $this->state(fn (array $attributes) => [
            'duration' => fake()->numberBetween(1200, 7200),
        ]);
    }

    /**
     * Indicate that the video is popular (high view count).
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'view_count' => fake()->numberBetween(1000000, 100000000),
            'like_count' => fake()->numberBetween(100000, 5000000),
        ]);
    }
}
