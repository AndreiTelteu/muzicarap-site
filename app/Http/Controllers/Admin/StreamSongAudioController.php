<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Song;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamSongAudioController extends Controller
{
    public function __invoke(Song $song): StreamedResponse
    {
        abort_unless($song->audio_path !== null, Response::HTTP_NOT_FOUND);

        $disk = Storage::disk(config('filesystems.default'));
        abort_unless($disk->exists($song->audio_path), Response::HTTP_NOT_FOUND);

        return $disk->response($song->audio_path, basename($song->audio_path), [
            'Accept-Ranges' => 'bytes',
        ]);
    }
}
