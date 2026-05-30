<?php

namespace App\Mcp;

use App\Enums\AlbumType;
use App\Enums\SongParentType;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MusicCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function artists(): array
    {
        return Artist::query()
            ->withCount(['albums', 'songs'])
            ->orderBy('name')
            ->get()
            ->map(fn (Artist $artist): array => $this->serializeArtist($artist))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function albums(): array
    {
        return Album::query()
            ->with(['artist'])
            ->withCount(['songs'])
            ->orderBy('title')
            ->get()
            ->map(fn (Album $album): array => $this->serializeAlbum($album))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function songs(): array
    {
        return Song::query()
            ->with(['artist', 'album'])
            ->orderBy('title')
            ->get()
            ->map(fn (Song $song): array => $this->serializeSong($song))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createArtist(array $data): array
    {
        $name = trim((string) $data['name']);
        $slug = $this->resolveSlug(Artist::query(), $this->normalizeSlug($data['slug'] ?? $name));

        $artist = Artist::create([
            'name' => $name,
            'slug' => $slug,
            'bio' => $this->nullableString($data['bio'] ?? null),
            'image_path' => $this->nullableString($data['image_path'] ?? null),
            'is_published' => (bool) ($data['is_published'] ?? false),
        ]);

        return $this->serializeArtist($artist->loadCount(['albums', 'songs']));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createAlbum(array $data): array
    {
        $artist = Artist::query()->findOrFail((int) $data['artist_id']);
        $title = trim((string) $data['title']);
        $slug = $this->resolveSlug(
            Album::query()->where('artist_id', $artist->getKey()),
            $this->normalizeSlug($data['slug'] ?? $title),
        );
        $type = $data['type'] ?? AlbumType::Album->value;

        $album = Album::create([
            'artist_id' => $artist->getKey(),
            'title' => $title,
            'slug' => $slug,
            'type' => $type,
            'release_date' => $data['release_date'] ?? null,
            'cover_path' => $this->nullableString($data['cover_path'] ?? null),
            'description' => $this->nullableString($data['description'] ?? null),
        ]);

        return $this->serializeAlbum($album->load(['artist'])->loadCount(['songs']));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createSong(array $data): array
    {
        $artist = Artist::query()->findOrFail((int) $data['artist_id']);
        $album = null;

        if (filled($data['album_id'] ?? null)) {
            $album = Album::query()
                ->whereKey((int) $data['album_id'])
                ->where('artist_id', $artist->getKey())
                ->firstOrFail();
        }

        $title = trim((string) $data['title']);
        $slug = $this->resolveSlug(
            Song::query()->where('artist_id', $artist->getKey()),
            $this->normalizeSlug($data['slug'] ?? $title),
        );
        $youtubeId = $this->resolveYouTubeId((string) $data['youtube_url']);
        $parentType = $data['parent_type'] ?? ($album !== null ? SongParentType::Album->value : SongParentType::Single->value);

        $song = Song::create([
            'artist_id' => $artist->getKey(),
            'album_id' => $album?->getKey(),
            'title' => $title,
            'slug' => $slug,
            'track_number' => $this->nullableInteger($data['track_number'] ?? null),
            'parent_type' => $parentType,
            'duration_seconds' => $this->nullableInteger($data['duration_seconds'] ?? null),
            'audio_path' => $this->nullableString($data['audio_path'] ?? null),
            'youtube_id' => $youtubeId,
            'is_published' => (bool) ($data['is_published'] ?? false),
        ]);

        return $this->serializeSong($song->load(['artist', 'album']));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeArtist(Artist $artist): array
    {
        return [
            'id' => $artist->getKey(),
            'name' => $artist->name,
            'slug' => $artist->slug,
            'bio' => $artist->bio,
            'image_path' => $artist->image_path,
            'is_published' => $artist->is_published,
            'albums_count' => $artist->albums_count ?? $artist->albums()->count(),
            'songs_count' => $artist->songs_count ?? $artist->songs()->count(),
            'resource_uri' => 'music://artists/'.$artist->slug,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAlbum(Album $album): array
    {
        return [
            'id' => $album->getKey(),
            'artist' => [
                'id' => $album->artist?->getKey(),
                'name' => $album->artist?->name,
                'slug' => $album->artist?->slug,
            ],
            'title' => $album->title,
            'slug' => $album->slug,
            'type' => $album->type->value,
            'release_date' => $album->release_date?->toDateString(),
            'cover_path' => $album->cover_path,
            'description' => $album->description,
            'songs_count' => $album->songs_count ?? $album->songs()->count(),
            'resource_uri' => 'music://albums/'.$album->artist?->slug.'/'.$album->slug,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSong(Song $song): array
    {
        return [
            'id' => $song->getKey(),
            'artist' => [
                'id' => $song->artist?->getKey(),
                'name' => $song->artist?->name,
                'slug' => $song->artist?->slug,
            ],
            'album' => $song->album === null ? null : [
                'id' => $song->album->getKey(),
                'title' => $song->album->title,
                'slug' => $song->album->slug,
            ],
            'title' => $song->title,
            'slug' => $song->slug,
            'track_number' => $song->track_number,
            'parent_type' => $song->parent_type->value,
            'duration_seconds' => $song->duration_seconds,
            'audio_path' => $song->audio_path,
            'youtube_id' => $song->youtube_id,
            'youtube_url' => $song->youtube_id !== null ? 'https://www.youtube.com/watch?v='.$song->youtube_id : null,
            'is_published' => $song->is_published,
            'resource_uri' => 'music://songs/'.$song->artist?->slug.'/'.$song->slug,
        ];
    }

    private function normalizeSlug(string $value): string
    {
        return Str::slug(trim($value)) ?: 'item';
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function resolveSlug(Builder $query, string $slug): string
    {
        $baseSlug = $this->normalizeSlug($slug);
        $candidate = $baseSlug;
        $suffix = 2;

        while ((clone $query)->where('slug', $candidate)->exists()) {
            $candidate = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function resolveYouTubeId(string $input): string
    {
        $input = trim($input);

        if ($input === '') {
            throw new InvalidArgumentException('YouTube URL is required.');
        }

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input) === 1) {
            return $input;
        }

        $parts = parse_url($input);

        if ($parts === false) {
            throw new InvalidArgumentException('Invalid YouTube URL.');
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (str_contains($host, 'youtu.be')) {
            $videoId = explode('/', $path, 2)[0] ?? '';

            if ($videoId !== '') {
                return $videoId;
            }
        }

        if (str_contains($host, 'youtube.com') || str_contains($host, 'youtube-nocookie.com')) {
            if ($path === 'watch') {
                parse_str((string) ($parts['query'] ?? ''), $query);

                if (! empty($query['v'])) {
                    return (string) $query['v'];
                }
            }

            foreach (['embed/', 'shorts/', 'live/'] as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    $videoId = explode('/', $path, 2)[1] ?? '';

                    if ($videoId !== '') {
                        return $videoId;
                    }
                }
            }
        }

        throw new InvalidArgumentException('Unable to extract a YouTube video ID from the provided link.');
    }
}
