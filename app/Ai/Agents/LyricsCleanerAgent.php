<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::DeepSeek)]
#[Model('deepseek-v4-pro')]
#[Timeout(300)]
class LyricsCleanerAgent implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You clean raw scraped lyric candidates for a Romanian rap lyrics workflow.
Only accept content when it is clearly the lyrics for the requested song.
Remove site chrome, metadata, ads, duplicate repeated blocks, comments, and unrelated prose.
Preserve line breaks and verse ordering.
Reject if the text is mostly article copy, tracklists, biography, or too uncertain.
PROMPT;
    }

    /**
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['accepted', 'rejected'])->required(),
            'clean_lyrics' => $schema->string()->nullable(),
            'source_url' => $schema->string()->nullable(),
            'confidence_score' => $schema->number()->required(),
            'notes' => $schema->string()->required(),
        ];
    }
}
