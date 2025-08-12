<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;

class UserProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create profiles for existing users
        User::all()->each(function ($user) {
            if (!$user->profile) {
                // Check if username is already taken in user_profiles
                $existingProfile = UserProfile::where('username', $user->username)->first();
                $username = $existingProfile ? $user->username . '_' . $user->id : $user->username;

                UserProfile::create([
                    'user_id' => $user->id,
                    'username' => $username,
                    'bio' => fake()->paragraph(),
                    'avatar' => null,
                    'website' => fake()->optional()->url(),
                    'location' => fake()->optional()->city(),
                    'social_links' => [
                        'twitter' => fake()->optional()->url(),
                        'youtube' => fake()->optional()->url(),
                        'instagram' => fake()->optional()->url(),
                    ],
                    'is_verified' => fake()->boolean(20),
                    'is_featured_curator' => fake()->boolean(10),
                    'follower_count' => fake()->numberBetween(0, 1000),
                    'following_count' => fake()->numberBetween(0, 500),
                    'collection_count' => fake()->numberBetween(0, 50),
                ]);
            }
        });
    }
}
