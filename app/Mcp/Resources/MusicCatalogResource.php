<?php

namespace App\Mcp\Resources;

use App\Mcp\MusicCatalog;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('list')]
#[Uri('music://catalog')]
#[Description('List artists, albums, and songs as a single catalog resource.')]
class MusicCatalogResource extends Resource
{
    protected string $mimeType = 'application/json';

    public function handle(Request $request, MusicCatalog $catalog): Response
    {
        return Response::json([
            'artists' => $catalog->artists(),
            'albums' => $catalog->albums(),
            'songs' => $catalog->songs(),
        ]);
    }
}
