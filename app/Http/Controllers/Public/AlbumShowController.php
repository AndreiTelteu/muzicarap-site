<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Support\PublicCatalogData;
use Inertia\Inertia;
use Inertia\Response;

class AlbumShowController extends Controller
{
    public function __invoke(Artist $artist, Album $album): Response
    {
        abort_unless($artist->is_published, 404);
        abort_unless($album->artist_id === $artist->id, 404);

        $album->load([
            'latestPublishedSongWithThumbnail',
            'artist.latestPublishedSongWithThumbnail',
            'songs' => fn ($query) => $query->published()->orderByRaw('track_number is null')->orderBy('track_number')->orderBy('title'),
        ]);

        return Inertia::render('Public/Albums/Show', [
            'artist' => PublicCatalogData::artistSummary($artist),
            'album' => PublicCatalogData::albumSummary($artist, $album),
            'tracks' => $album->songs->map(fn (Song $song): array => [
                ...PublicCatalogData::songSummary($song),
                'track_number' => $song->track_number,
            ])->values()->all(),
        ]);
    }
}
