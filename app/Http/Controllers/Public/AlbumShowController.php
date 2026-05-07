<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use Inertia\Inertia;
use Inertia\Response;

class AlbumShowController extends Controller
{
    public function __invoke(Artist $artist, Album $album): Response
    {
        abort_unless($artist->is_published, 404);
        abort_unless($album->artist_id === $artist->id, 404);

        $album->load([
            'songs' => fn ($query) => $query->published()->orderByRaw('track_number is null')->orderBy('track_number')->orderBy('title'),
        ]);

        return Inertia::render('Public/Albums/Show', [
            'artist' => [
                'name' => $artist->name,
                'slug' => $artist->slug,
                'url' => route('artists.show', $artist),
            ],
            'album' => [
                'title' => $album->title,
                'slug' => $album->slug,
                'type' => $album->type->value,
                'description' => $album->description,
                'release_date' => $album->release_date?->toDateString(),
                'cover_path' => $album->cover_path,
            ],
            'tracks' => $album->songs->map(fn (Song $song): array => [
                'title' => $song->title,
                'slug' => $song->slug,
                'track_number' => $song->track_number,
                'duration_seconds' => $song->duration_seconds,
                'url' => route('artists.songs.show', [$artist, $song]),
            ])->values()->all(),
        ]);
    }
}
