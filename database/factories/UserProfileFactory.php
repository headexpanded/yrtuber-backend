<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserProfile>
 */
class UserProfileFactory extends Factory
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
            'username' => fake()->unique()->userName(),
            'bio' => fake()->paragraph(),
            'avatar' => fake()->optional()->imageUrl(),
            'website' => fake()->optional()->url(),
            'location' => fake()->optional()->city(),
            'social_links' => [
                'twitter' => fake()->optional()->url(),
                'youtube' => fake()->optional()->url(),
                'instagram' => fake()->optional()->url(),
                'tiktok' => fake()->optional()->url(),
            ],
            'is_verified' => fake()->boolean(20),
            'is_featured_curator' => fake()->boolean(10),
            'follower_count' => fake()->numberBetween(0, 1000),
            'following_count' => fake()->numberBetween(0, 500),
            'collection_count' => fake()->numberBetween(0, 50),
        ];
    }

    /**
     * Indicate that the user is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
        ]);
    }

    /**
     * Indicate that the user is a featured curator.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured_curator' => true,
        ]);
    }

    /**
     * Indicate that the user has no social links.
     */
    public function noSocialLinks(): static
    {
        return $this->state(fn (array $attributes) => [
            'social_links' => [],
        ]);
    }

    /**
     * Indicate that the profile has default values.
     */
    public function withDefaults(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => false,
            'is_featured_curator' => false,
            'follower_count' => 0,
            'following_count' => 0,
            'collection_count' => 0,
        ]);
    }
}
