<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Like>
 */
class LikeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'likeable_type' => Collection::class,
            'likeable_id' => Collection::factory(),
        ];
    }

    /**
     * Indicate that the like is for a collection.
     */
    public function forCollection(): static
    {
        return $this->state(fn (array $attributes) => [
            'likeable_type' => Collection::class,
            'likeable_id' => Collection::factory(),
        ]);
    }

    /**
     * Indicate that the like is for a video.
     */
    public function forVideo(): static
    {
        return $this->state(fn (array $attributes) => [
            'likeable_type' => Video::class,
            'likeable_id' => Video::factory(),
        ]);
    }
}
