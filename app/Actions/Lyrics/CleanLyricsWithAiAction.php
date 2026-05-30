<?php

namespace App\Actions\Lyrics;

use App\Ai\Agents\LyricsCleanerAgent;
use App\Models\Song;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

class CleanLyricsWithAiAction
{
    /**
     * @param  list<array{url:string,title:string,snippet:string,text:string}>  $candidates
     * @return array{status:string,clean_lyrics:string|null,source_url:string|null,confidence_score:float,notes:string}
     */
    public function handle(Song $song, array $candidates): array
    {
        $prompt = $this->buildPrompt($song, $candidates);

        Log::info('Lyrics cleaner prompt prepared.', [
            'song_id' => $song->getKey(),
            'candidate_count' => count($candidates),
            'prompt_length' => mb_strlen($prompt),
        ]);

        $response = LyricsCleanerAgent::make()->prompt(
            prompt: $prompt,
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
     *
     * @throws JsonException
     */
    private function buildPrompt(Song $song, array $candidates): string
    {
        try {
            return json_encode([
                'artist' => $song->artist->name,
                'song' => $song->title,
                'album' => $song->album?->title,
                'candidates' => $candidates,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::warning('Lyrics cleaner prompt encoding failed.', [
                'song_id' => $song->getKey(),
                'candidate_count' => count($candidates),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
