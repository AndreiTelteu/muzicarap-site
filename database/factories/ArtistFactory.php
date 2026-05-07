<?php

namespace Database\Factories;

use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Artist>
 */
class ArtistFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'bio' => fake()->optional()->paragraphs(asText: true),
            'image_path' => null,
            'is_published' => true,
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
