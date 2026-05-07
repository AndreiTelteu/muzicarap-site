<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Song;
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
            ->map(fn (Song $song): array => [
                'title' => $song->title,
                'slug' => $song->slug,
                'artist' => [
                    'name' => $song->artist->name,
                    'slug' => $song->artist->slug,
                    'url' => route('artists.show', $song->artist),
                ],
                'album' => $song->album?->title,
                'parent_type' => $song->parent_type->value,
                'duration_seconds' => $song->duration_seconds,
                'url' => route('artists.songs.show', [$song->artist, $song]),
                'created_at' => $song->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('Home', [
            'latestSongs' => $latestSongs,
        ]);
    }
}
