<?php

namespace Database\Factories;

use App\Models\Keyword;
use App\Models\KeywordThreshold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KeywordThreshold>
 */
class KeywordThresholdFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'keyword_id' => Keyword::factory(),
            'group' => 0,
            'metric' => $this->faker->randomElement(['views', 'likes', 'replies', 'reposts', 'quotes']),
            'operator' => $this->faker->randomElement(['>', '>=', '=', '<', '<=']),
            'value' => $this->faker->numberBetween(10, 100000),
        ];
    }
}
