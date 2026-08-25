<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // 404 rather than 403: the existence of the back office is not something
        // a stranger needs confirmed.
        abort_unless($request->user()?->isAdmin(), 404);

        return $next($request);
    }
}
