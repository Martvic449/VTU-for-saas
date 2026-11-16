<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() && $request->user()->status === 'active', 403, 'Account is inactive.');
        return $next($request);
    }
}
