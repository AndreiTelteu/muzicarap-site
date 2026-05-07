<?php

namespace App\Actions\Lyrics;

use App\Enums\LyricSourceStatus;
use App\Models\Lyric;
use App\Models\Song;
use Illuminate\Support\Facades\DB;

class SaveLyricsSyncAction
{
    public function __construct(
        private readonly SegmentLyricsTextAction $segmentLyricsText,
    ) {}

    /**
     * @param  array{
     *     lyrics:string,
     *     segments:list<array{
     *         text:string,
     *         starts_at_ms:int|null,
     *         ends_at_ms:int|null,
     *         is_instrumental_gap:bool
     *     }>
     * }  $payload
     */
    public function handle(Song $song, array $payload): Lyric
    {
        return DB::transaction(function () use ($song, $payload): Lyric {
            $lyric = $song->lyric()->firstOrCreate([], [
                'lyrics' => '',
                'source_status' => LyricSourceStatus::Manual,
            ]);

            $lyrics = trim($payload['lyrics']);
            $segments = $payload['segments'];

            if ($lyrics !== '' && $segments === []) {
                $segments = $this->segmentLyricsText->handle($lyrics);
            }

            $lyric->fill([
                'lyrics' => $lyrics,
                'source_status' => LyricSourceStatus::Manual,
                'synced_at' => $this->allSegmentsStamped($segments) ? now() : null,
            ]);
            $lyric->save();

            $lyric->segments()->delete();

            foreach ($segments as $index => $segment) {
                $lyric->segments()->create([
                    'position' => $index + 1,
                    'text' => trim($segment['text']),
                    'starts_at_ms' => $segment['starts_at_ms'],
                    'ends_at_ms' => $segment['ends_at_ms'],
                    'is_instrumental_gap' => $segment['is_instrumental_gap'],
                ]);
            }

            return $lyric->load('segments');
        });
    }

    /**
     * @param  list<array{starts_at_ms:int|null}>  $segments
     */
    private function allSegmentsStamped(array $segments): bool
    {
        if ($segments === []) {
            return false;
        }

        foreach ($segments as $segment) {
            if ($segment['starts_at_ms'] === null) {
                return false;
            }
        }

        return true;
    }
}
