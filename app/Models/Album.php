<?php

namespace App\Models;

use App\Enums\AlbumType;
use Database\Factories\AlbumFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Album extends Model
{
    /** @use HasFactory<AlbumFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'artist_id',
        'title',
        'slug',
        'type',
        'release_date',
        'cover_path',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'type' => AlbumType::class,
        ];
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function songs(): HasMany
    {
        return $this->hasMany(Song::class);
    }

    public function latestPublishedSongWithThumbnail(): HasOne
    {
        return $this->hasOne(Song::class)
            ->published()
            ->whereNotNull('image_path')
            ->latestOfMany('created_at');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
