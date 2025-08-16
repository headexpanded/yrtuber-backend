<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use App\Models\Collection;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;
use JetBrains\PhpStorm\ArrayShape;

/**
 * @extends Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    #[ArrayShape([
        'user_id' => "mixed",
        'notifiable_type' => "\class-string",
        'notifiable_id' => "\Closure",
        'type' => "mixed",
        'actor_id' => "mixed",
        'subject_type' => "mixed",
        'subject_id' => "\Closure",
        'data' => "array",
        'read_at' => "null"
    ])] public function definition(): array
    {
        $types = [
            'collection_liked',
            'video_liked',
            'comment_added',
            'user_followed',
            'collection_shared',
        ];

        $subjectTypes = [
            Collection::class,
            Video::class,
            User::class,
        ];

        $subjectType = $this->faker->randomElement($subjectTypes);
        $type = $this->faker->randomElement($types);

        return [
            'user_id' => User::factory(), // Who receives the notification
            'notifiable_type' => User::class,
            'notifiable_id' => function (array $attributes) {
                return $attributes['user_id'];
            },
            'type' => $type,
            'actor_id' => User::factory(), // Who triggered the notification
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
            'data' => [
                'action' => $this->faker->randomElement([
                    'liked your collection',
                    'liked your video',
                    'commented on your collection',
                    'started following you',
                    'shared your collection',
                ]),
                'actor_name' => $this->faker->userName(),
                'subject_title' => $this->faker->sentence(3),
                'subject_type' => class_basename($subjectType),
            ],
            'read_at' => null,
        ];
    }

    /**
     * Indicate that the notification is read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the notification is unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    /**
     * Indicate that the notification is for collection liked.
     */
    public function collectionLiked(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'collection_liked',
            'subject_type' => Collection::class,
            'data' => [
                'action' => 'liked your collection',
                'actor_name' => $this->faker->userName(),
                'subject_title' => $this->faker->sentence(3),
                'subject_type' => 'Collection',
            ],
        ]);
    }

    /**
     * Indicate that the notification is for video liked.
     */
    public function videoLiked(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'video_liked',
            'subject_type' => Video::class,
            'data' => [
                'action' => 'liked your video',
                'actor_name' => $this->faker->userName(),
                'subject_title' => $this->faker->sentence(3),
                'subject_type' => 'Video',
            ],
        ]);
    }

    /**
     * Indicate that the notification is for comment added.
     */
    public function commentAdded(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'comment_added',
            'subject_type' => Collection::class,
            'data' => [
                'action' => 'commented on your collection',
                'actor_name' => $this->faker->userName(),
                'subject_title' => $this->faker->sentence(3),
                'subject_type' => 'Collection',
            ],
        ]);
    }

    /**
     * Indicate that the notification is for user followed.
     */
    public function userFollowed(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'user_followed',
            'subject_type' => User::class,
            'data' => [
                'action' => 'started following you',
                'actor_name' => $this->faker->userName(),
                'subject_title' => $this->faker->userName(),
                'subject_type' => 'User',
            ],
        ]);
    }
}
