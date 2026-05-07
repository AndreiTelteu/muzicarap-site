<?php

namespace Database\Factories;

use App\Models\LyricsCrawlRun;
use App\Enums\LyricsCrawlStatus;
use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LyricsCrawlRun>
 */
class LyricsCrawlRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_urls' => [],
            'failure_reason' => null,
            'finished_at' => null,
            'response_snapshot' => null,
            'search_query' => fake()->sentence(4),
            'selected_url' => null,
            'song_id' => Song::factory(),
            'started_at' => now(),
            'status' => LyricsCrawlStatus::Queued,
        ];
    }
}
