<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Lyrics\StartLyricsCrawlAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\DispatchLyricsCrawlRequest;
use App\Models\Song;
use Illuminate\Http\JsonResponse;

class DispatchSongLyricsCrawlController extends Controller
{
    public function __invoke(
        DispatchLyricsCrawlRequest $request,
        Song $song,
        StartLyricsCrawlAction $startLyricsCrawl,
    ): JsonResponse {
        $run = $startLyricsCrawl->handle($song->loadMissing('artist'));

        return response()->json([
            'run' => [
                'id' => $run->getKey(),
                'status' => $run->status->value,
                'search_query' => $run->search_query,
            ],
        ], 202);
    }
}
