<?php

namespace App\Mcp\Tools;

use App\Mcp\MusicCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_song')]
#[Description('Create a new song in the music catalog and store its YouTube video ID.')]
class CreateSong extends Tool
{
    public function handle(Request $request, MusicCatalog $catalog): Response
    {
        $data = $request->validate([
            'artist_id' => ['required', 'integer', 'exists:artists,id'],
            'album_id' => ['nullable', 'integer', 'exists:albums,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'track_number' => ['nullable', 'integer', 'min:1'],
            'parent_type' => ['nullable', 'string', 'in:album,ep,single'],
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'audio_path' => ['nullable', 'string', 'max:255'],
            'youtube_url' => ['required', 'string', 'max:2048'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        return Response::json([
            'song' => $catalog->createSong($data),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'artist_id' => $schema->integer()->description('Artist ID')->required(),
            'album_id' => $schema->integer()->description('Optional album ID.'),
            'title' => $schema->string()->description('Song title')->required(),
            'slug' => $schema->string()->description('Optional slug. Defaults to a slugified version of the title.'),
            'track_number' => $schema->integer()->description('Optional track number.'),
            'parent_type' => $schema->string()->description('Song parent type: album, ep, or single.'),
            'duration_seconds' => $schema->integer()->description('Optional duration in seconds.'),
            'audio_path' => $schema->string()->description('Optional path to the audio file.'),
            'youtube_url' => $schema->string()->description('YouTube link or 11-character video ID.')->required(),
            'is_published' => $schema->boolean()->description('Whether the song should be published.'),
        ];
    }
}
