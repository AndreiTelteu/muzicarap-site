<?php

namespace App\Models;

use App\Enums\LyricsCrawlStatus;
use Database\Factories\LyricsCrawlRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LyricsCrawlRun extends Model
{
    /** @use HasFactory<LyricsCrawlRunFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'song_id',
        'status',
        'search_query',
        'candidate_urls',
        'selected_url',
        'failure_reason',
        'response_snapshot',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'candidate_urls' => 'array',
            'finished_at' => 'datetime',
            'response_snapshot' => 'array',
            'started_at' => 'datetime',
            'status' => LyricsCrawlStatus::class,
        ];
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }
}
