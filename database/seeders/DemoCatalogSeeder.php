<?php

namespace Database\Seeders;

use App\Enums\AlbumType;
use App\Enums\LyricSourceStatus;
use App\Enums\SongParentType;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Lyric;
use App\Models\Song;
use Illuminate\Database\Seeder;

class DemoCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $artist = Artist::query()->firstOrCreate(
            ['slug' => 'bug-mafia'],
            [
                'name' => 'B.U.G. Mafia',
                'bio' => 'Demo catalog artist for local administration.',
                'is_published' => true,
            ],
        );

        $album = Album::query()->firstOrCreate(
            ['artist_id' => $artist->id, 'slug' => 'de-cartier'],
            [
                'title' => 'De Cartier',
                'type' => AlbumType::Album,
                'release_date' => '1998-01-01',
            ],
        );

        $song = Song::query()->firstOrCreate(
            ['artist_id' => $artist->id, 'slug' => 'romaneste'],
            [
                'album_id' => $album->id,
                'title' => 'Romaneste',
                'parent_type' => SongParentType::Album,
                'track_number' => 1,
                'duration_seconds' => 216,
                'is_published' => true,
            ],
        );

        $lyrics = Lyric::query()->firstOrCreate(
            ['song_id' => $song->id],
            [
                'lyrics' => implode("\n", [
                    'Romaneste, bine ai venit in cartier',
                    'Aici fiecare vers are foc si caracter',
                    'Ridica boxele, lasa ritmul sa vorbeasca',
                ]),
                'source_status' => LyricSourceStatus::Manual,
            ],
        );

        if (! $lyrics->segments()->exists()) {
            $lyrics->segments()->createMany([
                [
                    'position' => 1,
                    'text' => 'Romaneste, bine ai venit in cartier',
                    'starts_at_ms' => 0,
                    'ends_at_ms' => 2600,
                ],
                [
                    'position' => 2,
                    'text' => 'Aici fiecare vers are foc si caracter',
                    'starts_at_ms' => 2600,
                    'ends_at_ms' => 5600,
                ],
                [
                    'position' => 3,
                    'text' => 'Ridica boxele, lasa ritmul sa vorbeasca',
                    'starts_at_ms' => 5600,
                    'ends_at_ms' => 8600,
                ],
            ]);
        }
    }
}
