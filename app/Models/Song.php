<?php

namespace App\Models;

use App\Enums\SongParentType;
use Database\Factories\SongFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Song extends Model
{
    /** @use HasFactory<SongFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'artist_id',
        'album_id',
        'title',
        'slug',
        'track_number',
        'parent_type',
        'duration_seconds',
        'audio_path',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_seconds' => 'int',
            'is_published' => 'bool',
            'parent_type' => SongParentType::class,
            'track_number' => 'int',
        ];
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function lyric(): HasOne
    {
        return $this->hasOne(Lyric::class);
    }

    public function crawlRuns(): HasMany
    {
        return $this->hasMany(LyricsCrawlRun::class);
    }

    public function latestCrawlRun(): HasOne
    {
        return $this->hasOne(LyricsCrawlRun::class)->latestOfMany();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeMissingLyrics(Builder $query): Builder
    {
        return $query->whereDoesntHave('lyric');
    }

    public function scopeUnsyncedLyrics(Builder $query): Builder
    {
        return $query->whereHas('lyric', fn ($lyricQuery) => $lyricQuery->whereNull('synced_at'));
    }

    public function scopeMissingAudio(Builder $query): Builder
    {
        return $query->whereNull('audio_path');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function hasPublicAudio(): bool
    {
        return $this->audio_path !== null && $this->is_published;
    }
}
