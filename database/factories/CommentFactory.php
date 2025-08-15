<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
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
            'commentable_type' => Collection::class,
            'commentable_id' => Collection::factory(),
            'content' => fake()->paragraph(),
        ];
    }

    /**
     * Indicate that the comment is for a collection.
     */
    public function forCollection(): static
    {
        return $this->state(fn (array $attributes) => [
            'commentable_type' => Collection::class,
            'commentable_id' => Collection::factory(),
        ]);
    }

    /**
     * Indicate that the comment is for a video.
     */
    public function forVideo(): static
    {
        return $this->state(fn (array $attributes) => [
            'commentable_type' => Video::class,
            'commentable_id' => Video::factory(),
        ]);
    }
}
