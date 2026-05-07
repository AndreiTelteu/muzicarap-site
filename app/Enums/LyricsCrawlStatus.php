<?php

namespace App\Enums;

enum LyricsCrawlStatus: string
{
    case Queued = 'queued';
    case Searching = 'searching';
    case Crawling = 'crawling';
    case Cleaning = 'cleaning';
    case Stored = 'stored';
    case Failed = 'failed';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => ucfirst($status->value)])
            ->all();
    }
}
