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
use Illuminate\Support\Str;
//use Illuminate\Queue\Attributes\Timeout;
use Throwable;

//#[Timeout(300)]
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

        Log::info('Lyrics crawl started.', [
            'song_id' => $song->getKey(),
            'crawl_run_id' => $run->getKey(),
            'artist' => $song->artist?->name,
            'song' => $song->title,
            'search_query' => $run->search_query,
        ]);

        $run->update([
            'status' => LyricsCrawlStatus::Searching,
            'started_at' => $run->started_at ?? now(),
        ]);

        $candidates = $search->search($run->search_query);

        Log::info('Lyrics crawl search candidates resolved.', [
            'song_id' => $song->getKey(),
            'crawl_run_id' => $run->getKey(),
            'search_query' => $run->search_query,
            'candidate_count' => count($candidates),
            'candidates' => collect($candidates)
                ->map(fn (array $candidate): array => [
                    'url' => $candidate['url'],
                    'title' => $candidate['title'],
                    'snippet' => $this->preview($candidate['snippet']),
                    'score' => $candidate['score'],
                ])
                ->values()
                ->all(),
        ]);

        if ($candidates === []) {
            $this->markFailedRun($song, $run, 'No search candidates were returned.');

            return;
        }

        $run->update([
            'status' => LyricsCrawlStatus::Crawling,
            'candidate_urls' => collect($candidates)->pluck('url')->take(10)->all(),
        ]);

        $pages = $fetcher->fetch($candidates);

        Log::info('Lyrics crawl candidate pages fetched.', [
            'song_id' => $song->getKey(),
            'crawl_run_id' => $run->getKey(),
            'page_count' => count($pages),
            'pages' => collect($pages)
                ->map(fn (array $page): array => [
                    'url' => $page['url'],
                    'title' => $page['title'],
                    'snippet' => $this->preview($page['snippet']),
                    'status' => $page['status'],
                    'content_type' => $page['content_type'],
                    'html_length' => mb_strlen($page['html']),
                    'html_preview' => $this->preview($page['html'], 1500),
                ])
                ->values()
                ->all(),
        ]);

        if ($pages === []) {
            $this->markFailedRun($song, $run, 'No crawlable lyric pages were found.');

            return;
        }

        $run->update(['status' => LyricsCrawlStatus::Cleaning]);

        $cleaningPayload = collect($pages)
            ->flatMap(function (array $page) use ($extractor, $song, $run): array {
                $blocks = $extractor->extract($page['html']);

                Log::info('Lyrics crawl extracted text blocks from page.', [
                    'song_id' => $song->getKey(),
                    'crawl_run_id' => $run->getKey(),
                    'url' => $page['url'],
                    'title' => $page['title'],
                    'extracted_block_count' => count($blocks),
                    'blocks' => collect($blocks)
                        ->map(fn (array $block): array => [
                            'label' => $block['label'],
                            'text_length' => mb_strlen($block['text']),
                            'text_preview' => $this->preview($block['text'], 2000),
                        ])
                        ->values()
                        ->all(),
                ]);

                return collect($blocks)
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

        Log::info('Lyrics crawl cleaning payload prepared.', [
            'song_id' => $song->getKey(),
            'crawl_run_id' => $run->getKey(),
            'payload_count' => count($cleaningPayload),
            'payload' => collect($cleaningPayload)
                ->map(fn (array $candidate): array => [
                    'url' => $candidate['url'],
                    'title' => $candidate['title'],
                    'snippet' => $this->preview($candidate['snippet']),
                    'text_length' => mb_strlen($candidate['text']),
                    'text_preview' => $this->preview($candidate['text'], 2500),
                ])
                ->values()
                ->all(),
        ]);

        if ($cleaningPayload === []) {
            $this->markFailedRun($song, $run, 'Crawler could not extract enough text from candidate pages.');

            return;
        }

        $result = $cleanLyricsWithAi->handle($song, $cleaningPayload);

        Log::info('Lyrics crawl AI cleaning finished.', [
            'song_id' => $song->getKey(),
            'crawl_run_id' => $run->getKey(),
            'status' => $result['status'],
            'source_url' => $result['source_url'],
            'confidence_score' => $result['confidence_score'],
            'notes' => $result['notes'],
            'clean_lyrics_length' => filled($result['clean_lyrics']) ? mb_strlen($result['clean_lyrics']) : 0,
            'clean_lyrics_preview' => filled($result['clean_lyrics']) ? $this->preview($result['clean_lyrics'], 2000) : null,
        ]);

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

    private function preview(string $value, int $limit = 500): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $value) ?? $value), $limit);
    }
}
