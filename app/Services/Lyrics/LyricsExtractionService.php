<?php

namespace App\Services\Lyrics;

use Symfony\Component\DomCrawler\Crawler;

class LyricsExtractionService
{
    /**
     * @return list<array{label:string,text:string}>
     */
    public function extract(string $html): array
    {
        $crawler = new Crawler($html);
        $blocks = [];

        foreach (['.lyrics', '[class*="lyric"]', '[id*="lyric"]', 'article', 'main', 'body'] as $selector) {
            foreach ($crawler->filter($selector) as $node) {
                $block = $this->normalizeHtmlBlock($node->ownerDocument->saveHTML($node) ?: '');

                if (mb_strlen($block) < 60) {
                    continue;
                }

                $blocks[] = [
                    'label' => $selector,
                    'text' => $block,
                ];
            }
        }

        return collect($blocks)
            ->unique('text')
            ->sortByDesc(fn (array $block): int => mb_strlen($block['text']))
            ->take(5)
            ->values()
            ->all();
    }

    private function normalizeHtmlBlock(string $html): string
    {
        $html = preg_replace('/<(script|style|noscript)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<br\s*\/?\s*>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(p|div|li|section|article|h\d)>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\h+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
