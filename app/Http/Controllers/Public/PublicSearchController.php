<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Support\PublicCatalogData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->string('query'));

        if ($query === '') {
            return response()->json([
                'query' => '',
                'artists' => [],
                'albums' => [],
                'songs' => [],
            ]);
        }

        $artists = Artist::query()
            ->published()
            ->where('name', 'like', "%{$query}%")
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Artist $artist): array => PublicCatalogData::artistSummary($artist))
            ->values()
            ->all();

        $albums = Album::query()
            ->whereHas('artist', fn ($queryBuilder) => $queryBuilder->published())
            ->whereHas('songs', fn ($queryBuilder) => $queryBuilder->published())
            ->with('artist')
            ->where('title', 'like', "%{$query}%")
            ->orderByDesc('release_date')
            ->orderBy('title')
            ->limit(8)
            ->get()
            ->map(fn (Album $album): array => PublicCatalogData::albumSummary($album->artist, $album))
            ->values()
            ->all();

        $songs = Song::query()
            ->published()
            ->whereHas('artist', fn ($queryBuilder) => $queryBuilder->published())
            ->with(['artist', 'album'])
            ->where(function ($queryBuilder) use ($query): void {
                $queryBuilder
                    ->where('title', 'like', "%{$query}%")
                    ->orWhereHas('artist', fn ($artistQuery) => $artistQuery->where('name', 'like', "%{$query}%"))
                    ->orWhereHas('album', fn ($albumQuery) => $albumQuery->where('title', 'like', "%{$query}%"));
            })
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (Song $song): array => PublicCatalogData::songSummary($song))
            ->values()
            ->all();

        return response()->json([
            'query' => $query,
            'artists' => $artists,
            'albums' => $albums,
            'songs' => $songs,
        ]);
    }
}
