<?php

namespace App\Jobs;

use App\Actions\Lyrics\CleanLyricsWithAiAction;
use App\Actions\Lyrics\StoreLyricsFromCrawlAction;
use App\Enums\LyricsCrawlStatus;
use App\Enums\LyricSourceStatus;
use App\Models\LyricsCrawlRun;
use App\Models\Song;
use App\Services\Lyrics\LyricsCandidateFetcher;
use App\Services\Lyrics\LyricsExtractionService;
use App\Services\Lyrics\SearxNgLyricsSearchService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class CrawlLyricsForSongJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $songId,
        public readonly int $crawlRunId,
    ) {}

    public function uniqueId(): string
    {
        return 'lyrics-crawl-song-'.$this->songId;
    }

    public function handle(
        SearxNgLyricsSearchService $search,
        LyricsCandidateFetcher $fetcher,
        LyricsExtractionService $extractor,
        CleanLyricsWithAiAction $cleanLyricsWithAi,
        StoreLyricsFromCrawlAction $storeLyricsFromCrawl,
    ): void {
        $song = Song::query()->with(['artist', 'album', 'lyric', 'crawlRuns'])->findOrFail($this->songId);
        $run = LyricsCrawlRun::query()->findOrFail($this->crawlRunId);

        $run->update([
            'status' => LyricsCrawlStatus::Searching,
            'started_at' => $run->started_at ?? now(),
        ]);

        $candidates = $search->search($run->search_query);

        if ($candidates === []) {
            $this->markFailedRun($song, $run, 'No search candidates were returned.');

            return;
        }

        $run->update([
            'status' => LyricsCrawlStatus::Crawling,
            'candidate_urls' => collect($candidates)->pluck('url')->take(10)->all(),
        ]);

        $pages = $fetcher->fetch($candidates);

        if ($pages === []) {
            $this->markFailedRun($song, $run, 'No crawlable lyric pages were found.');

            return;
        }

        $run->update(['status' => LyricsCrawlStatus::Cleaning]);

        $cleaningPayload = collect($pages)
            ->flatMap(function (array $page) use ($extractor): array {
                return collect($extractor->extract($page['html']))
                    ->map(fn (array $block): array => [
                        'url' => $page['url'],
                        'title' => $page['title'],
                        'snippet' => $page['snippet'],
                        'text' => $block['text'],
                    ])
                    ->all();
            })
            ->take(5)
            ->values()
            ->all();

        if ($cleaningPayload === []) {
            $this->markFailedRun($song, $run, 'Crawler could not extract enough text from candidate pages.');

            return;
        }

        $result = $cleanLyricsWithAi->handle($song, $cleaningPayload);

        if ($result['status'] !== 'accepted' || blank($result['clean_lyrics'])) {
            $this->markFailedRun($song, $run, $result['notes'] !== '' ? $result['notes'] : 'AI rejected all lyric candidates.');

            return;
        }

        $storeLyricsFromCrawl->handle($song, $run, [
            'clean_lyrics' => $result['clean_lyrics'],
            'source_url' => $result['source_url'],
            'confidence_score' => $result['confidence_score'],
            'notes' => $result['notes'],
        ]);

        $run->update([
            'status' => LyricsCrawlStatus::Stored,
            'finished_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $run = LyricsCrawlRun::query()->find($this->crawlRunId);
        $song = Song::query()->with('lyric')->find($this->songId);

        if ($run !== null && $song !== null) {
            $this->markFailedRun($song, $run, $exception?->getMessage() ?? 'Lyrics crawl job failed.');
        }
    }

    private function markFailedRun(Song $song, LyricsCrawlRun $run, string $reason): void
    {
        $run->update([
            'status' => LyricsCrawlStatus::Failed,
            'failure_reason' => $reason,
            'finished_at' => now(),
        ]);

        $song->lyric()?->update([
            'source_status' => LyricSourceStatus::Failed,
        ]);

        Log::warning('Lyrics crawl failed.', [
            'song_id' => $song->getKey(),
            'crawl_run_id' => $run->getKey(),
            'reason' => $reason,
        ]);
    }
}
