<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Song;
use Inertia\Inertia;
use Inertia\Response;

class ArtistShowController extends Controller
{
    public function __invoke(Artist $artist): Response
    {
        abort_unless($artist->is_published, 404);

        $artist->load([
            'albums' => fn ($query) => $query
                ->whereHas('songs', fn ($songQuery) => $songQuery->published())
                ->withCount([
                    'songs' => fn ($songQuery) => $songQuery->published(),
                ])
                ->orderByDesc('release_date')
                ->orderBy('title'),
            'songs' => fn ($query) => $query->published()->with('album')->latest()->orderBy('track_number'),
        ]);

        return Inertia::render('Public/Artists/Show', [
            'artist' => [
                'name' => $artist->name,
                'slug' => $artist->slug,
                'bio' => $artist->bio,
                'image_path' => $artist->image_path,
            ],
            'albums' => $artist->albums->map(fn ($album): array => [
                'title' => $album->title,
                'slug' => $album->slug,
                'type' => $album->type->value,
                'release_date' => $album->release_date?->toDateString(),
                'songs_count' => $album->songs_count,
                'url' => route('artists.albums.show', [$artist, $album]),
            ])->values()->all(),
            'songs' => $artist->songs->map(fn (Song $song): array => [
                'title' => $song->title,
                'slug' => $song->slug,
                'parent_type' => $song->parent_type->value,
                'album' => $song->album?->title,
                'duration_seconds' => $song->duration_seconds,
                'url' => route('artists.songs.show', [$artist, $song]),
            ])->values()->all(),
        ]);
    }
}
