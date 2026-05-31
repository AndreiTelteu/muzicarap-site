<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Song;
use App\Support\PublicCatalogData;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $latestSongs = Song::query()
            ->published()
            ->whereHas('artist', fn ($query) => $query->published())
            ->with(['artist', 'album'])
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (Song $song): array => PublicCatalogData::songSummary($song))
            ->values()
            ->all();

        $featuredArtists = Artist::query()
            ->published()
            ->with('latestPublishedSongWithThumbnail')
            ->withCount([
                'songs' => fn ($query) => $query->published(),
                'albums' => fn ($query) => $query->whereHas('songs', fn ($songQuery) => $songQuery->published()),
            ])
            ->orderByDesc('songs_count')
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Artist $artist): array => [
                ...PublicCatalogData::artistSummary($artist),
                'songs_count' => $artist->songs_count,
                'albums_count' => $artist->albums_count,
            ])
            ->values()
            ->all();

        return Inertia::render('Home', [
            'latestSongs' => $latestSongs,
            'featuredArtists' => $featuredArtists,
        ]);
    }
}
