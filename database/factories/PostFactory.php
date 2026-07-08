<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = now();

        return [
            'threads_url' => 'https://www.threads.net/@' . $this->faker->userName() . '/post/' . $this->faker->uuid(),
            'author_name' => $this->faker->name(),
            'author_username' => $this->faker->userName(),
            'posted_at' => $now,
            'content' => $this->faker->sentence(),
            'images' => [],
            'videos' => [],
            'views_count' => $this->faker->numberBetween(0, 100000),
            'likes_count' => $this->faker->numberBetween(0, 10000),
            'replies_count' => $this->faker->numberBetween(0, 1000),
            'reposts_count' => $this->faker->numberBetween(0, 1000),
            'quotes_count' => $this->faker->numberBetween(0, 500),
            'is_verified_author' => $this->faker->boolean(),
            'first_seen_at' => $now,
            'last_seen_at' => $now,
        ];
    }
}
