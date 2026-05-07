<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return new RedirectResponse(Filament::getPanel('admin')->getLoginUrl());
        }

        abort_unless($user->is_admin, Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
