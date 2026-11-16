<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTenantMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->attributes->get('tenant');
        $user = $request->user();
        abort_unless($tenant && $user, 404);
        abort_unless($user->status === 'active', 403, 'Account is inactive.');
        abort_unless($user->tenants()->whereKey($tenant->id)->wherePivot('status', 'active')->exists(), 403);
        return $next($request);
    }
}
