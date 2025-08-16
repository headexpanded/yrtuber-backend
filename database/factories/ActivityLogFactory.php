<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Collection;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ActivityLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $actions = [
            'collection.created',
            'collection.updated',
            'collection.liked',
            'video.added',
            'video.liked',
            'comment.added',
            'user.followed',
        ];

        $subjectTypes = [
            Collection::class,
            Video::class,
            User::class,
        ];

        $subjectType = $this->faker->randomElement($subjectTypes);
        $action = $this->faker->randomElement($actions);

        return [
            'user_id' => User::factory(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => function () use ($subjectType) {
                if ($subjectType === Collection::class) {
                    return Collection::factory();
                } elseif ($subjectType === Video::class) {
                    return Video::factory();
                } else {
                    return User::factory();
                }
            },
            'target_user_id' => User::factory(),
            'properties' => [
                'subject_title' => $this->faker->sentence(3),
                'subject_type' => class_basename($subjectType),
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
            ],
            'visibility' => $this->faker->randomElement(['public', 'private', 'followers']),
            'aggregated_count' => $this->faker->numberBetween(1, 5),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }

    /**
     * Indicate that the activity log is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'public',
        ]);
    }

    /**
     * Indicate that the activity log is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'private',
        ]);
    }

    /**
     * Indicate that the activity log is for followers only.
     */
    public function followers(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'followers',
        ]);
    }

    /**
     * Indicate that the activity log is aggregated.
     */
    public function aggregated(int $count = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'aggregated_count' => $count,
        ]);
    }
}
