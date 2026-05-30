<?php

namespace App\Mcp\Tools;

use App\Mcp\MusicCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_album')]
#[Description('Create a new album or EP in the music catalog.')]
class CreateAlbum extends Tool
{
    public function handle(Request $request, MusicCatalog $catalog): Response
    {
        $data = $request->validate([
            'artist_id' => ['required', 'integer', 'exists:artists,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:album,ep'],
            'release_date' => ['nullable', 'date'],
            'cover_path' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        return Response::json([
            'album' => $catalog->createAlbum($data),
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
            'title' => $schema->string()->description('Album title')->required(),
            'slug' => $schema->string()->description('Optional slug. Defaults to a slugified version of the title.'),
            'type' => $schema->string()->description('Album type: album or ep.'),
            'release_date' => $schema->string()->description('Optional release date in YYYY-MM-DD format.'),
            'cover_path' => $schema->string()->description('Optional path to the cover image.'),
            'description' => $schema->string()->description('Optional album description.'),
        ];
    }
}
