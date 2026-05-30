<?php

use App\Mcp\Servers\MusicCatalogServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', MusicCatalogServer::class);
