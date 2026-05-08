<?php

namespace App\Services\Songs;

use App\Models\Song;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SearxNgYouTubeSearchService
{
    /**
     * @return list<array{url:string,title:string,snippet:string,score:int}>
     */
    public function search(Song $song): array
    {
        $song->loadMissing('artist');

        $response = Http::timeout((int) config('muzicarap.song_audio.request_timeout', 15))
            ->connectTimeout((int) config('muzicarap.song_audio.connect_timeout', 5))
            ->retry(2, 250)
            ->acceptJson()
            ->get(config('muzicarap.song_audio.searxng_url'), [
                'q' => $this->buildQuery($song),
                'format' => 'json',
                'categories' => config('muzicarap.song_audio.searxng_categories', 'videos'),
            ])
            ->throw();

        return collect($response->json('results', []))
            ->map(function (array $result) use ($song): ?array {
                $url = Arr::get($result, 'url');

                if (! is_string($url) || ! Str::startsWith($url, ['http://', 'https://']) || ! $this->isYouTubeUrl($url)) {
                    return null;
                }

                $title = trim((string) Arr::get($result, 'title', ''));
                $snippet = trim((string) Arr::get($result, 'content', ''));

                return [
                    'url' => $url,
                    'title' => $title,
                    'snippet' => $snippet,
                    'score' => $this->scoreCandidate($song, $url, $title, $snippet),
                ];
            })
            ->filter()
            ->unique('url')
            ->sortByDesc('score')
            ->take((int) config('muzicarap.song_audio.max_candidates', 5))
            ->values()
            ->all();
    }

    private function buildQuery(Song $song): string
    {
        return trim(sprintf('%s %s site:youtube.com/watch', $song->artist->name, $song->title));
    }

    private function isYouTubeUrl(string $url): bool
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'youtu.be'
            || $host === 'www.youtu.be'
            || $host === 'youtube.com'
            || $host === 'www.youtube.com'
            || $host === 'm.youtube.com';
    }

    private function scoreCandidate(Song $song, string $url, string $title, string $snippet): int
    {
        $haystack = Str::lower($url.' '.$title.' '.$snippet);
        $score = 0;
        $artistName = Str::lower($song->artist->name);
        $songTitle = Str::lower($song->title);

        if (Str::contains($haystack, $artistName)) {
            $score += 40;
        }

        if (Str::contains($haystack, $songTitle)) {
            $score += 50;
        }

        foreach (['official audio', 'audio', 'topic', 'provided to youtube', 'hq'] as $needle) {
            if (Str::contains($haystack, $needle)) {
                $score += 12;
            }
        }

        foreach (['lyrics', 'karaoke', 'instrumental', 'remix', 'live', 'concert', 'cover', 'reaction', 'slowed', 'sped up', 'nightcore', '8d'] as $needle) {
            if (Str::contains($haystack, $needle)) {
                $score -= 20;
            }
        }

        return $score;
    }
}
