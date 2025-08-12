<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Database\Seeder;

class CollectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create collections for existing users
        User::all()->each(function ($user) {
            // Create 2-5 collections per user
            $collectionCount = fake()->numberBetween(2, 5);

            Collection::factory($collectionCount)
                ->for($user)
                ->create();
        });
    }
}
