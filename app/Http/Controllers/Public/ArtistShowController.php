<?php

namespace App\Http\Controllers\Public;

use App\Enums\SongParentType;
use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Song;
use App\Support\PublicCatalogData;
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
            'songs' => fn ($query) => $query
                ->published()
                ->whereNull('album_id')
                ->where('parent_type', SongParentType::Single)
                ->with('album')
                ->latest()
                ->orderBy('track_number'),
        ]);

        return Inertia::render('Public/Artists/Show', [
            'artist' => PublicCatalogData::artistSummary($artist),
            'albums' => $artist->albums->map(fn ($album): array => [
                ...PublicCatalogData::albumSummary($artist, $album, $album->songs_count),
                'songs_count' => $album->songs_count,
            ])->values()->all(),
            'songs' => $artist->songs->map(fn (Song $song): array => PublicCatalogData::songSummary($song))->values()->all(),
        ]);
    }
}
