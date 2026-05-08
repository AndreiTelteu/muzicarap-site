<?php

namespace App\Jobs;

use App\Models\Song;
use App\Services\Songs\SearxNgYouTubeSearchService;
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

    public function handle(
        SearxNgYouTubeSearchService $search,
        YouTubeMp3Downloader $downloader,
    ): void {
        $song = Song::query()->with('artist')->findOrFail($this->songId);

        if ($song->audio_path !== null) {
            return;
        }

        $candidate = collect($search->search($song))->first();

        if (! is_array($candidate) || ! isset($candidate['url']) || ! is_string($candidate['url'])) {
            throw new RuntimeException('No YouTube candidate was found for the song.');
        }

        $storedPath = null;

        try {
            $storedPath = $downloader->download($song, $candidate['url']);

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
