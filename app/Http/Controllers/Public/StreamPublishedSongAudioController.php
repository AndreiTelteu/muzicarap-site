<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Song;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamPublishedSongAudioController extends Controller
{
    public function __invoke(Artist $artist, Song $song): StreamedResponse
    {
        abort_unless($artist->is_published, 404);
        abort_unless($song->artist_id === $artist->id && $song->hasPublicAudio(), 404);

        $disk = Storage::disk(config('filesystems.default'));
        abort_unless($disk->exists($song->audio_path), 404);

        return $disk->response($song->audio_path, basename($song->audio_path), [
            'Accept-Ranges' => 'bytes',
            'Content-Type' => 'audio/mpeg',
        ]);
    }
}
