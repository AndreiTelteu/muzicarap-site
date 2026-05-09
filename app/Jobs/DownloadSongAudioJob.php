<?php

namespace App\Jobs;

use App\Models\Song;
use App\Services\Songs\YouTubeMp3Downloader;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DownloadSongAudioJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 80;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $songId) {}

    public function uniqueId(): string
    {
        return 'song-audio-download-'.$this->songId;
    }

    public function handle(YouTubeMp3Downloader $downloader): void
    {
        $song = Song::query()->with('artist')->findOrFail($this->songId);

        if ($song->audio_path !== null) {
            return;
        }

        if (! is_string($song->youtube_id) || $song->youtube_id === '') {
            throw new RuntimeException('No YouTube ID is set for the song.');
        }

        $storedPath = null;

        try {
            $storedPath = $downloader->download($song, 'https://www.youtube.com/watch?v='.$song->youtube_id);

            $song->forceFill([
                'audio_path' => $storedPath,
            ])->save();
        } catch (Throwable $exception) {
            if (is_string($storedPath) && $storedPath !== '') {
                Storage::disk(config('filesystems.default'))->delete($storedPath);
            }

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Song audio download failed.', [
            'song_id' => $this->songId,
            'reason' => $exception?->getMessage() ?? 'Song audio download job failed.',
        ]);
    }
}
