<?php

namespace App\Enums;

enum SongParentType: string
{
    case Album = 'album';
    case Ep = 'ep';
    case Single = 'single';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => ucfirst($type->value)])
            ->all();
    }
}
