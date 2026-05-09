<?php

namespace App\Jobs;

use App\Models\Song;
use App\Services\Songs\SearxNgYouTubeSearchService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SyncSongYoutubeIdJob implements ShouldBeUnique, ShouldQueue
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
        return 'song-youtube-id-sync-'.$this->songId;
    }

    public function handle(SearxNgYouTubeSearchService $search): void
    {
        $song = Song::query()->with('artist')->findOrFail($this->songId);

        if ($song->youtube_id !== null) {
            return;
        }

        $youtubeId = $search->bestMatchingYouTubeId($song);

        if ($youtubeId === null) {
            throw new RuntimeException('No YouTube candidate was found for the song.');
        }

        $song->forceFill([
            'youtube_id' => $youtubeId,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Song YouTube ID sync failed.', [
            'song_id' => $this->songId,
            'reason' => $exception?->getMessage() ?? 'Song YouTube ID sync job failed.',
        ]);
    }
}
