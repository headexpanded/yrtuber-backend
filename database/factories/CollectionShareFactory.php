<?php

namespace Database\Factories;

use App\Models\CollectionShare;
use App\Models\User;
use App\Models\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use JetBrains\PhpStorm\ArrayShape;

/**
 * @extends Factory<\App\Models\CollectionShare>
 */
class CollectionShareFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CollectionShare::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    #[ArrayShape([
        'collection_id' => "mixed",
        'user_id' => "mixed",
        'platform' => "mixed",
        'url' => "string",
        'share_type' => "mixed",
        'shared_at' => "\DateTime",
        'expires_at' => "\DateTime|null",
        'metadata' => "array",
        'analytics' => "array"
    ])] public function definition(): array
    {
        $platforms = ['twitter', 'facebook', 'linkedin', 'email', 'link', 'iframe'];
        $shareTypes = ['public', 'private', 'temporary'];

        $platform = $this->faker->randomElement($platforms);
        $shareType = $this->faker->randomElement($shareTypes);

        $url = match ($platform) {
            'twitter' => 'https://twitter.com/intent/tweet?url=' . $this->faker->url(),
            'facebook' => 'https://facebook.com/sharer/sharer.php?u=' . $this->faker->url(),
            'linkedin' => 'https://linkedin.com/sharing/share-offsite/?url=' . $this->faker->url(),
            'email' => 'mailto:?subject=' . $this->faker->sentence(3) . '&body=' . $this->faker->url(),
            'link' => $this->faker->url(),
            'iframe' => $this->faker->url(),
            default => $this->faker->url(),
        };

        return [
            'collection_id' => Collection::factory(),
            'user_id' => User::factory(),
            'platform' => $platform,
            'url' => $url,
            'share_type' => $shareType,
            'shared_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'expires_at' => $shareType === 'temporary'
                ? $this->faker->dateTimeBetween('now', '+1 month')
                : null,
            'metadata' => [
                'platform' => $platform,
                'share_type' => $shareType,
                'original_url' => $this->faker->url(),
                'custom_message' => $this->faker->optional()->sentence(),
            ],
            'analytics' => [
                'clicks' => $this->faker->numberBetween(0, 100),
                'views' => $this->faker->numberBetween(0, 500),
                'last_click' => $this->faker->optional()->dateTimeBetween('-1 week', 'now')->format('Y-m-d H:i:s'),
                'last_view' => $this->faker->optional()->dateTimeBetween('-1 week', 'now')->format('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * Indicate that the share is for Twitter.
     */
    public function twitter(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'twitter',
            'url' => 'https://twitter.com/intent/tweet?url=' . $this->faker->url(),
        ]);
    }

    /**
     * Indicate that the share is for Facebook.
     */
    public function facebook(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'facebook',
            'url' => 'https://facebook.com/sharer/sharer.php?u=' . $this->faker->url(),
        ]);
    }

    /**
     * Indicate that the share is for LinkedIn.
     */
    public function linkedin(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'linkedin',
            'url' => 'https://linkedin.com/sharing/share-offsite/?url=' . $this->faker->url(),
        ]);
    }

    /**
     * Indicate that the share is for email.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'email',
            'url' => 'mailto:?subject=' . $this->faker->sentence(3) . '&body=' . $this->faker->url(),
        ]);
    }

    /**
     * Indicate that the share is for iframe.
     */
    public function iframe(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'iframe',
            'url' => $this->faker->url(),
        ]);
    }

    /**
     * Indicate that the share is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'share_type' => 'public',
            'expires_at' => null,
        ]);
    }

    /**
     * Indicate that the share is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'share_type' => 'private',
            'expires_at' => null,
        ]);
    }

    /**
     * Indicate that the share is temporary.
     */
    public function temporary(): static
    {
        return $this->state(fn (array $attributes) => [
            'share_type' => 'temporary',
            'expires_at' => $this->faker->dateTimeBetween('now', '+1 month'),
        ]);
    }

    /**
     * Indicate that the share has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }

    /**
     * Indicate that the share has analytics.
     */
    public function withAnalytics(): static
    {
        return $this->state(fn (array $attributes) => [
            'analytics' => [
                'clicks' => $this->faker->numberBetween(10, 100),
                'views' => $this->faker->numberBetween(50, 500),
                'last_click' => $this->faker->dateTimeBetween('-1 week', 'now')->format('Y-m-d H:i:s'),
                'last_view' => $this->faker->dateTimeBetween('-1 week', 'now')->format('Y-m-d H:i:s'),
            ],
        ]);
    }
}
