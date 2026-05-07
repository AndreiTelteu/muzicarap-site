<?php

namespace App\Services\Lyrics;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SearxNgLyricsSearchService
{
    /**
     * @return list<array{url:string,title:string,snippet:string,score:int}>
     */
    public function search(string $query): array
    {
        $response = Http::timeout((int) config('muzicarap.crawl.request_timeout', 15))
            ->connectTimeout((int) config('muzicarap.crawl.connect_timeout', 5))
            ->retry(2, 250)
            ->acceptJson()
            ->get(config('muzicarap.crawl.searxng_url', 'http://192.168.0.115:8388/search'), [
                'q' => $query,
                'format' => 'json',
            ])
            ->throw();

        $results = collect($response->json('results', []))
            ->map(function (array $result): ?array {
                $url = Arr::get($result, 'url');

                if (! is_string($url) || ! Str::startsWith($url, ['http://', 'https://'])) {
                    return null;
                }

                $title = trim((string) Arr::get($result, 'title', ''));
                $snippet = trim((string) Arr::get($result, 'content', ''));

                return [
                    'url' => $url,
                    'title' => $title,
                    'snippet' => $snippet,
                    'score' => $this->scoreCandidate($url, $title, $snippet),
                ];
            })
            ->filter()
            ->unique('url')
            ->sortByDesc('score')
            ->take((int) config('muzicarap.crawl.max_candidates', 5))
            ->values()
            ->all();

        return $results;
    }

    private function scoreCandidate(string $url, string $title, string $snippet): int
    {
        $haystack = Str::lower($url.' '.$title.' '.$snippet);
        $score = 0;

        foreach (['versuri', 'lyrics', 'lyric', 'genius', 'azlyrics'] as $needle) {
            if (Str::contains($haystack, $needle)) {
                $score += 10;
            }
        }

        foreach (['facebook', 'instagram', 'youtube', 'spotify', 'apple.com'] as $needle) {
            if (Str::contains($haystack, $needle)) {
                $score -= 20;
            }
        }

        return $score;
    }
}
