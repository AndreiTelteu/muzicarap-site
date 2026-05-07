<?php

use App\Ai\Agents\LyricsCleanerAgent;
use App\Services\Lyrics\LyricsCandidateFetcher;
use App\Services\Lyrics\SearxNgLyricsSearchService;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Enums\Lab;

it('pins the lyrics cleaner agent to deepseek v4 pro', function (): void {
    $reflection = new ReflectionClass(LyricsCleanerAgent::class);

    $provider = $reflection->getAttributes(Provider::class)[0]->newInstance();
    $model = $reflection->getAttributes(Model::class)[0]->newInstance();

    expect($provider->value)->toBe(Lab::DeepSeek)
        ->and($model->value)->toBe('deepseek-v4-pro');
});

it('uses the configured crawl limits and timeouts for search and fetch steps', function (): void {
    config()->set('muzicarap.crawl.searxng_url', 'https://searx.test/search');
    config()->set('muzicarap.crawl.max_candidates', 3);
    config()->set('muzicarap.crawl.fetch_top_results', 2);
    config()->set('muzicarap.crawl.request_timeout', 9);
    config()->set('muzicarap.crawl.connect_timeout', 4);

    $searchOptions = null;
    $fetchOptions = [];

    Http::fake(function ($request, array $options) use (&$searchOptions, &$fetchOptions) {
        if (str_starts_with($request->url(), 'https://searx.test/search')) {
            $searchOptions = $options;

            return Http::response([
                'results' => [
                    [
                        'url' => 'https://lyrics.example.com/track-one',
                        'title' => 'Track One Lyrics',
                        'content' => 'versuri track one',
                    ],
                    [
                        'url' => 'https://genius.example.com/track-two',
                        'title' => 'Track Two',
                        'content' => 'lyrics track two',
                    ],
                    [
                        'url' => 'https://azlyrics.example.com/track-three',
                        'title' => 'Track Three',
                        'content' => 'lyric track three',
                    ],
                ],
            ]);
        }

        $fetchOptions[$request->url()] = $options;

        return Http::response('<html><body>Lyrics block</body></html>', 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    });

    $candidates = app(SearxNgLyricsSearchService::class)->search('BUG Mafia versuri');
    $pages = app(LyricsCandidateFetcher::class)->fetch($candidates);

    expect($candidates)->toHaveCount(3)
        ->and($pages)->toHaveCount(2)
        ->and($searchOptions)->not->toBeNull()
        ->and($searchOptions['timeout'])->toBe(9)
        ->and($searchOptions['connect_timeout'])->toBe(4)
        ->and($fetchOptions)->toHaveCount(2);

    collect($fetchOptions)->each(function (array $options): void {
        expect($options['timeout'])->toBe(9)
            ->and($options['connect_timeout'])->toBe(4);
    });
});
