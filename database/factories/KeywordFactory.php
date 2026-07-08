<?php

namespace Database\Factories;

use App\Models\Keyword;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Keyword>
 */
class KeywordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'is_active' => true,
            'crawl_interval_min' => 10,
            'time_range_type' => '24h',
            'time_range_custom_from' => null,
            'time_range_custom_to' => null,
            'last_crawled_at' => null,
        ];
    }
}
