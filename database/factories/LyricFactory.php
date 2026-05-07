<?php

namespace Database\Factories;

use App\Models\Lyric;
use App\Enums\LyricSourceStatus;
use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lyric>
 */
class LyricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crawl_confidence' => null,
            'external_source_url' => null,
            'lyrics' => implode("\n", [
                fake()->sentence(6),
                fake()->sentence(6),
                fake()->sentence(6),
            ]),
            'song_id' => Song::factory(),
            'source_status' => LyricSourceStatus::Manual,
            'synced_at' => null,
        ];
    }
}
