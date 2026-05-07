<?php

namespace Database\Factories;

use App\Models\LyricSegment;
use App\Models\Lyric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LyricSegment>
 */
class LyricSegmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ends_at_ms' => fake()->numberBetween(2500, 5000),
            'is_instrumental_gap' => false,
            'lyric_id' => Lyric::factory(),
            'position' => 1,
            'starts_at_ms' => 0,
            'text' => fake()->sentence(),
        ];
    }
}
