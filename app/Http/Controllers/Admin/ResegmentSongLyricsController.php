<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Lyrics\SegmentLyricsTextAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResegmentLyricsRequest;
use App\Models\Song;
use Illuminate\Http\JsonResponse;

class ResegmentSongLyricsController extends Controller
{
    public function __invoke(
        ResegmentLyricsRequest $request,
        Song $song,
        SegmentLyricsTextAction $segmentLyricsText,
    ): JsonResponse {
        $segments = $segmentLyricsText->handle($request->string('lyrics')->toString());

        return response()->json([
            'lyrics' => $request->string('lyrics')->toString(),
            'segments' => $segments,
        ]);
    }
}
