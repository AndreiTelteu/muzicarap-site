<?php

namespace Database\Factories;

use App\Models\Song;
use App\Enums\SongParentType;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Song>
 */
class SongFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(fake()->numberBetween(2, 5), true));

        return [
            'album_id' => null,
            'artist_id' => Artist::factory(),
            'audio_path' => null,
            'duration_seconds' => fake()->numberBetween(120, 360),
            'is_published' => true,
            'parent_type' => SongParentType::Single,
            'slug' => Str::slug($title),
            'title' => $title,
            'track_number' => null,
        ];
    }
}
