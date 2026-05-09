<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Song;
use Inertia\Inertia;
use Inertia\Response;

class SongShowController extends Controller
{
    public function __invoke(Artist $artist, Song $song): Response
    {
        abort_unless($artist->is_published, 404);
        abort_unless($song->artist_id === $artist->id && $song->is_published, 404);

        $song->loadMissing(['album', 'lyric.segments']);

        $lyric = $song->lyric;
        $segments = $lyric?->segments ?? collect();
        $hasTimedSegments = $segments->contains(fn ($segment): bool => $segment->starts_at_ms !== null);

        return Inertia::render('Public/Songs/Show', [
            'artist' => [
                'name' => $artist->name,
                'slug' => $artist->slug,
                'url' => route('artists.show', $artist),
            ],
            'song' => [
                'title' => $song->title,
                'slug' => $song->slug,
                'album' => $song->album?->title,
                'album_url' => $song->album ? route('artists.albums.show', [$artist, $song->album]) : null,
                'duration_seconds' => $song->duration_seconds,
                'parent_type' => $song->parent_type->value,
                'youtube_id' => $song->youtube_id,
            ],
            'lyrics' => [
                'text' => $lyric?->lyrics ?? '',
                'is_synced' => $lyric !== null && $lyric->synced_at !== null && $hasTimedSegments,
                'segments' => $segments->map(fn ($segment): array => [
                    'id' => $segment->id,
                    'position' => $segment->position,
                    'text' => $segment->text,
                    'starts_at_ms' => $segment->starts_at_ms,
                    'ends_at_ms' => $segment->ends_at_ms,
                    'is_instrumental_gap' => $segment->is_instrumental_gap,
                ])->values()->all(),
            ],
        ]);
    }
}
