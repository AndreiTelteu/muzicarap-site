<?php

namespace App\Enums;

enum LyricSourceStatus: string
{
    case Manual = 'manual';
    case Queued = 'queued';
    case Crawled = 'crawled';
    case Cleaned = 'cleaned';
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
