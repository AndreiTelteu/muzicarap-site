<?php

namespace App\Models;

use App\Enums\LyricSourceStatus;
use Database\Factories\LyricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lyric extends Model
{
    /** @use HasFactory<LyricFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'song_id',
        'lyrics',
        'external_source_url',
        'source_status',
        'synced_at',
        'crawl_confidence',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'crawl_confidence' => 'decimal:2',
            'source_status' => LyricSourceStatus::class,
            'synced_at' => 'datetime',
        ];
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(LyricSegment::class)->orderBy('position');
    }

    public function isSynced(): bool
    {
        return $this->synced_at !== null
            && $this->segments()->whereNotNull('starts_at_ms')->exists();
    }
}
