<?php

namespace Database\Factories;

use App\Models\Album;
use App\Enums\AlbumType;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Album>
 */
class AlbumFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(3, true));

        return [
            'artist_id' => Artist::factory(),
            'cover_path' => null,
            'description' => fake()->optional()->paragraphs(2, true),
            'release_date' => fake()->optional()->date(),
            'slug' => Str::slug($title),
            'title' => $title,
            'type' => fake()->randomElement(AlbumType::cases()),
        ];
    }
}
