<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\MusicCatalogResource;
use App\Mcp\Tools\CreateAlbum;
use App\Mcp\Tools\CreateArtist;
use App\Mcp\Tools\CreateSong;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('music-catalog')]
#[Version('0.1.0')]
#[Instructions('Use the list resource to inspect artists, albums, and songs. Use create_artist, create_album, and create_song to add new records. create_song accepts a YouTube link and stores the extracted video id. ')]
class MusicCatalogServer extends Server
{
    protected array $tools = [
        CreateArtist::class,
        CreateAlbum::class,
        CreateSong::class,
    ];

    protected array $resources = [
        MusicCatalogResource::class,
    ];

    protected array $prompts = [
        //
    ];
}
