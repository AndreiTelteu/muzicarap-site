<?php

namespace App\Support;

use App\Models\Album;
use App\Models\Artist;
use App\Models\LyricSegment;
use App\Models\Song;
use Illuminate\Support\Facades\Storage;

class PublicCatalogData
{
    /**
     * @return array<string, mixed>
     */
    public static function artistSummary(Artist $artist): array
    {
        return [
            'name' => $artist->name,
            'slug' => $artist->slug,
            'bio' => $artist->bio,
            'image_url' => self::mediaUrl($artist->image_path),
            'url' => route('artists.show', $artist),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function albumSummary(Artist $artist, Album $album, ?int $songsCount = null): array
    {
        return [
            'title' => $album->title,
            'slug' => $album->slug,
            'type' => $album->type->value,
            'release_date' => $album->release_date?->toDateString(),
            'songs_count' => $songsCount,
            'cover_url' => self::mediaUrl($album->cover_path),
            'description' => $album->description,
            'artist' => [
                'name' => $artist->name,
                'slug' => $artist->slug,
                'url' => route('artists.show', $artist),
            ],
            'url' => route('artists.albums.show', [$artist, $album]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function songSummary(Song $song): array
    {
        $song->loadMissing(['artist', 'album']);

        return [
            'title' => $song->title,
            'slug' => $song->slug,
            'parent_type' => $song->parent_type->value,
            'album' => $song->album?->title,
            'album_slug' => $song->album?->slug,
            'album_url' => $song->album ? route('artists.albums.show', [$song->artist, $song->album]) : null,
            'cover_url' => self::songArtworkUrl($song),
            'duration_seconds' => $song->duration_seconds,
            'youtube_id' => $song->youtube_id,
            'player_url' => route('artists.songs.player', [$song->artist, $song]),
            'url' => route('artists.songs.show', [$song->artist, $song]),
            'created_at' => $song->created_at?->toIso8601String(),
            'artist' => [
                'name' => $song->artist->name,
                'slug' => $song->artist->slug,
                'image_url' => self::mediaUrl($song->artist->image_path),
                'url' => route('artists.show', $song->artist),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function songPagePayload(Artist $artist, Song $song): array
    {
        $song->loadMissing(['album', 'lyric.segments', 'artist']);

        $lyric = $song->lyric;
        $segments = $lyric?->segments ?? collect();
        $hasTimedSegments = $segments->contains(fn (LyricSegment $segment): bool => $segment->starts_at_ms !== null);

        return [
            'artist' => self::artistSummary($artist),
            'song' => self::songSummary($song),
            'lyrics' => [
                'text' => $lyric?->lyrics ?? '',
                'is_synced' => $lyric !== null && $lyric->synced_at !== null && $hasTimedSegments,
                'segments' => $segments->map(fn (LyricSegment $segment): array => [
                    'id' => $segment->id,
                    'position' => $segment->position,
                    'text' => $segment->text,
                    'starts_at_ms' => $segment->starts_at_ms,
                    'ends_at_ms' => $segment->ends_at_ms,
                    'is_instrumental_gap' => $segment->is_instrumental_gap,
                ])->values()->all(),
            ],
        ];
    }

    public static function mediaUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk(config('filesystems.default'))->url($path);
    }

    public static function songArtworkUrl(Song $song): ?string
    {
        return self::mediaUrl($song->album?->cover_path)
            ?? self::mediaUrl($song->artist->image_path);
    }
}
