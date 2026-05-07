<?php

namespace App\Services\Lyrics;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LyricsCandidateFetcher
{
    /**
     * @param  list<array{url:string,title:string,snippet:string,score:int}>  $candidates
     * @return list<array{url:string,title:string,snippet:string,html:string,content_type:string|null,status:int}>
     */
    public function fetch(array $candidates, ?int $limit = null): array
    {
        $limit ??= (int) config('muzicarap.crawl.fetch_top_results', 3);

        return collect($candidates)
            ->take($limit)
            ->map(function (array $candidate): ?array {
                $response = Http::timeout((int) config('muzicarap.crawl.request_timeout', 15))
                    ->connectTimeout((int) config('muzicarap.crawl.connect_timeout', 5))
                    ->retry(2, 500)
                    ->withHeaders([
                        'User-Agent' => 'MuzicaRapBot/1.0 (+lyrics crawl)',
                        'Accept' => 'text/html,application/xhtml+xml',
                    ])
                    ->get($candidate['url']);

                if (! $response->successful()) {
                    return null;
                }

                $contentType = $response->header('Content-Type');

                if ($contentType !== null && ! Str::contains(Str::lower($contentType), ['text/html', 'application/xhtml+xml'])) {
                    return null;
                }

                return [
                    'url' => $candidate['url'],
                    'title' => $candidate['title'],
                    'snippet' => $candidate['snippet'],
                    'html' => $response->body(),
                    'content_type' => $contentType,
                    'status' => $response->status(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
