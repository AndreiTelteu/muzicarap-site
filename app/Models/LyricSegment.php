<?php

namespace App\Models;

use Database\Factories\LyricSegmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LyricSegment extends Model
{
    /** @use HasFactory<LyricSegmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lyric_id',
        'position',
        'text',
        'starts_at_ms',
        'ends_at_ms',
        'is_instrumental_gap',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ends_at_ms' => 'int',
            'is_instrumental_gap' => 'bool',
            'position' => 'int',
            'starts_at_ms' => 'int',
        ];
    }

    public function lyric(): BelongsTo
    {
        return $this->belongsTo(Lyric::class);
    }
}
