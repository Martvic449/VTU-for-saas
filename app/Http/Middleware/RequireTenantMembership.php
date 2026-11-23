<?php

namespace App\\Http\\Middleware;

use Closure;
use Illuminate\\Http\\Request;
use Symfony\\Component\\HttpFoundation\\Response;

class RequireTenantMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 404);
        abort_unless($user->status === 'active', 403, 'Account is inactive.');

        $tenant = $request->attributes->get('tenant');

        // On the platform's own host, let an authenticated reseller owner
        // reach their single approved workspace without requiring a custom
        // DNS hostname to be live first. Tenant subdomains still resolve
        // strictly through ResolveTenantFromHost.
        if (! $tenant) {
            $host = strtolower(rtrim($request->getHost(), '.'));
            $platformHosts = config('tenancy.platform_hosts', []);
            $base = strtolower((string) config('tenancy.base_domain'));
            if ($base !== '') $platformHosts[] = $base;

            abort_unless(in_array($host, $platformHosts, true), 404);

            $owned = $user->tenants()
                ->where('tenants.status', 'active')
                ->wherePivot('status', 'active')
                ->wherePivot('role', 'tenant-owner')
                ->get();

            abort_unless($owned->count() === 1, $owned->isEmpty() ? 404 : 409, 'Select a single approved reseller workspace or contact platform support.');
            $tenant = $owned->first();
            $request->attributes->set('tenant', $tenant);
        }

        abort_unless($tenant->status === 'active', 404);
        abort_unless(
            $user->tenants()->whereKey($tenant->id)->wherePivot('status', 'active')->exists(),
            403
        );

        return $next($request);
    }
}
