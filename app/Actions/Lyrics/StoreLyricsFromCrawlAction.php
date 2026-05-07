<?php

namespace App\Actions\Lyrics;

use App\Enums\LyricSourceStatus;
use App\Models\Lyric;
use App\Models\LyricsCrawlRun;
use App\Models\Song;
use Illuminate\Support\Facades\DB;

class StoreLyricsFromCrawlAction
{
    public function __construct(
        private readonly SegmentLyricsTextAction $segmentLyricsText,
    ) {}

    /**
     * @param  array{clean_lyrics:string,source_url:string|null,confidence_score:float,notes:string}  $payload
     */
    public function handle(Song $song, LyricsCrawlRun $run, array $payload): Lyric
    {
        return DB::transaction(function () use ($song, $run, $payload): Lyric {
            $lyric = $song->lyric()->firstOrCreate([], [
                'lyrics' => '',
                'source_status' => LyricSourceStatus::Cleaned,
            ]);

            $lyric->fill([
                'lyrics' => trim($payload['clean_lyrics']),
                'external_source_url' => $payload['source_url'],
                'crawl_confidence' => $payload['confidence_score'],
                'source_status' => LyricSourceStatus::Cleaned,
                'synced_at' => null,
            ]);
            $lyric->save();

            $lyric->segments()->delete();

            foreach ($this->segmentLyricsText->handle($lyric->lyrics) as $segment) {
                $lyric->segments()->create($segment);
            }

            $run->update([
                'selected_url' => $payload['source_url'],
                'response_snapshot' => ['notes' => $payload['notes']],
            ]);

            return $lyric->load('segments');
        });
    }
}
