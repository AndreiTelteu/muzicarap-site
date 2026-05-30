<?php

namespace App\Mcp\Tools;

use App\Mcp\MusicCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_artist')]
#[Description('Create a new artist in the music catalog.')]
class CreateArtist extends Tool
{
    public function handle(Request $request, MusicCatalog $catalog): Response
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        return Response::json([
            'artist' => $catalog->createArtist($data),
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
            'name' => $schema->string()->description('Artist name')->required(),
            'slug' => $schema->string()->description('Optional slug. Defaults to a slugified version of the name.'),
            'bio' => $schema->string()->description('Optional artist bio.'),
            'image_path' => $schema->string()->description('Optional path to the artist image.'),
            'is_published' => $schema->boolean()->description('Whether the artist should be published.'),
        ];
    }
}
