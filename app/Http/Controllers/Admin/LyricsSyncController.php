<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Lyrics\SaveLyricsSyncAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveLyricsSyncRequest;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class LyricsSyncController extends Controller
{
    public function edit(Song $song): Response
    {
        $song->loadMissing(['artist', 'album', 'lyric.segments']);

        return Inertia::render('Admin/LyricsSync/Edit', [
            'song' => [
                'id' => $song->getKey(),
                'title' => $song->title,
                'artist' => $song->artist->name,
                'album' => $song->album?->title,
                'duration_seconds' => $song->duration_seconds,
                'audio_path' => $song->audio_path,
            ],
            'lyric' => [
                'id' => $song->lyric?->getKey(),
                'lyrics' => $song->lyric?->lyrics ?? '',
                'source_status' => $song->lyric?->source_status?->value,
                'synced_at' => $song->lyric?->synced_at?->toIso8601String(),
            ],
            'segments' => $song->lyric?->segments->map(fn ($segment): array => [
                'id' => $segment->getKey(),
                'position' => $segment->position,
                'text' => $segment->text,
                'starts_at_ms' => $segment->starts_at_ms,
                'ends_at_ms' => $segment->ends_at_ms,
                'is_instrumental_gap' => $segment->is_instrumental_gap,
            ])->values()->all() ?? [],
            'routes' => [
                'save' => route('admin.songs.lyrics-sync.update', $song),
                'resegment' => route('admin.songs.lyrics-sync.resegment', $song),
                'crawl' => route('admin.songs.lyrics-crawl.dispatch', $song),
                'audio' => $song->audio_path !== null ? route('admin.songs.audio.stream', $song) : null,
            ],
        ]);
    }

    public function update(SaveLyricsSyncRequest $request, Song $song, SaveLyricsSyncAction $saveLyricsSync): JsonResponse
    {
        $lyric = $saveLyricsSync->handle($song, $request->validated());

        return response()->json([
            'lyric' => [
                'id' => $lyric->getKey(),
                'lyrics' => $lyric->lyrics,
                'source_status' => $lyric->source_status->value,
                'synced_at' => $lyric->synced_at?->toIso8601String(),
                'updated_at' => $lyric->updated_at?->toIso8601String(),
            ],
            'segments' => $lyric->segments->map(fn ($segment): array => [
                'id' => $segment->getKey(),
                'position' => $segment->position,
                'text' => $segment->text,
                'starts_at_ms' => $segment->starts_at_ms,
                'ends_at_ms' => $segment->ends_at_ms,
                'is_instrumental_gap' => $segment->is_instrumental_gap,
            ])->values()->all(),
        ]);
    }
}
