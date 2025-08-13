<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Collection>
 */
class CollectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(),
            'cover_image' => fake()->optional()->imageUrl(),
            'layout' => fake()->randomElement(['grid', 'list', 'carousel', 'magazine']),
            'is_public' => fake()->boolean(80), // 80% chance of being public
            'is_featured' => fake()->boolean(10), // 10% chance of being featured
            'view_count' => fake()->numberBetween(0, 10000),
            'like_count' => fake()->numberBetween(0, 1000),
            'video_count' => fake()->numberBetween(0, 50),
        ];
    }

    /**
     * Indicate that the collection is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * Indicate that the collection is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }

    /**
     * Indicate that the collection is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    /**
     * Indicate that the collection has default values.
     */
    public function withDefaults(): static
    {
        return $this->state(fn (array $attributes) => [
            'layout' => 'grid',
            'is_public' => true,
            'is_featured' => false,
            'view_count' => 0,
            'like_count' => 0,
            'video_count' => 0,
        ]);
    }
}
