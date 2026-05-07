<?php

namespace App\Actions\Lyrics;

class SegmentLyricsTextAction
{
    /**
     * @return list<array{position:int,text:string,starts_at_ms:int|null,ends_at_ms:int|null,is_instrumental_gap:bool}>
     */
    public function handle(string $lyrics): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($lyrics));

        if ($normalized === '') {
            return [];
        }

        $lines = preg_split('/\n{1,}/', $normalized) ?: [];
        $segments = [];

        foreach ($lines as $index => $line) {
            $text = trim(preg_replace('/\h+/', ' ', $line) ?? $line);

            if ($text === '') {
                continue;
            }

            $segments[] = [
                'position' => count($segments) + 1,
                'text' => $text,
                'starts_at_ms' => null,
                'ends_at_ms' => null,
                'is_instrumental_gap' => false,
            ];
        }

        return $segments;
    }
}
