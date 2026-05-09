<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class FavoritesIndexController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Public/Favorites/Index');
    }
}
