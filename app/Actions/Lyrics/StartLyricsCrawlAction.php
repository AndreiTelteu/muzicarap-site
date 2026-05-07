<?php

namespace App\Actions\Lyrics;

use App\Enums\LyricsCrawlStatus;
use App\Enums\LyricSourceStatus;
use App\Jobs\CrawlLyricsForSongJob;
use App\Models\LyricsCrawlRun;
use App\Models\Song;

class StartLyricsCrawlAction
{
    public function handle(Song $song): LyricsCrawlRun
    {
        $song->lyric()->firstOrCreate([], [
            'lyrics' => '',
            'source_status' => LyricSourceStatus::Queued,
        ])->update([
            'source_status' => LyricSourceStatus::Queued,
            'synced_at' => null,
        ]);

        $run = $song->crawlRuns()->create([
            'status' => LyricsCrawlStatus::Queued,
            'search_query' => trim(sprintf('%s %s versuri', $song->artist->name, $song->title)),
            'started_at' => now(),
        ]);

        CrawlLyricsForSongJob::dispatch($song->getKey(), $run->getKey());

        return $run;
    }
}
