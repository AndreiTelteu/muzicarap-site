<?php

namespace App\Enums;

enum AlbumType: string
{
    case Album = 'album';
    case Ep = 'ep';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => strtoupper($type->value)])
            ->all();
    }
}
