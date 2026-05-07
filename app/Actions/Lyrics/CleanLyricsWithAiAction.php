<?php

namespace App\Actions\Lyrics;

use App\Ai\Agents\LyricsCleanerAgent;
use App\Models\Song;
use RuntimeException;

class CleanLyricsWithAiAction
{
    /**
     * @param  list<array{url:string,title:string,snippet:string,text:string}>  $candidates
     * @return array{status:string,clean_lyrics:string|null,source_url:string|null,confidence_score:float,notes:string}
     */
    public function handle(Song $song, array $candidates): array
    {
        $response = LyricsCleanerAgent::make()->prompt(
            prompt: $this->buildPrompt($song, $candidates),
            provider: 'deepseek',
            model: 'deepseek-v4-pro',
        );

        $structured = $response->structured ?? null;

        if (! is_array($structured)) {
            throw new RuntimeException('Lyrics cleaner agent did not return structured output.');
        }

        return [
            'status' => (string) ($structured['status'] ?? 'rejected'),
            'clean_lyrics' => isset($structured['clean_lyrics']) ? (string) $structured['clean_lyrics'] : null,
            'source_url' => isset($structured['source_url']) ? (string) $structured['source_url'] : null,
            'confidence_score' => (float) ($structured['confidence_score'] ?? 0),
            'notes' => (string) ($structured['notes'] ?? ''),
        ];
    }

    /**
     * @param  list<array{url:string,title:string,snippet:string,text:string}>  $candidates
     */
    private function buildPrompt(Song $song, array $candidates): string
    {
        return json_encode([
            'artist' => $song->artist->name,
            'song' => $song->title,
            'album' => $song->album?->title,
            'candidates' => $candidates,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
