<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Song;
use App\Support\PublicCatalogData;
use Illuminate\Http\JsonResponse;

class SongPlayerController extends Controller
{
    public function __invoke(Artist $artist, Song $song): JsonResponse
    {
        abort_unless($artist->is_published, 404);
        abort_unless($song->artist_id === $artist->id && $song->is_published, 404);

        return response()->json(PublicCatalogData::songPagePayload($artist, $song));
    }
}
