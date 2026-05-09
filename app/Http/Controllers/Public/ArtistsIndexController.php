<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Support\PublicCatalogData;
use Inertia\Inertia;
use Inertia\Response;

class ArtistsIndexController extends Controller
{
    public function __invoke(): Response
    {
        $artists = Artist::query()
            ->published()
            ->withCount([
                'songs' => fn ($query) => $query->published(),
                'albums' => fn ($query) => $query->whereHas('songs', fn ($songQuery) => $songQuery->published()),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Artist $artist): array => [
                ...PublicCatalogData::artistSummary($artist),
                'songs_count' => $artist->songs_count,
                'albums_count' => $artist->albums_count,
            ])
            ->values()
            ->all();

        return Inertia::render('Public/Artists/Index', [
            'artists' => $artists,
        ]);
    }
}
