<?php

namespace App\Http\Middleware;

use App\Models\TenantDomain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->remove('tenant');
        $host = strtolower(rtrim($request->getHost(), '.'));
        $platformHosts = config('tenancy.platform_hosts', []);
        $base = strtolower((string) config('tenancy.base_domain'));
        if ($base !== '') $platformHosts[] = $base;
        $domain = TenantDomain::query()->with('tenant')->where('hostname', $host)->first();

        if ($domain && (! $domain->verified_at || $domain->tls_status !== 'active' || ! $domain->tenant || $domain->tenant->status !== 'active')) {
            abort(404);
        }

        if ($domain && $domain->tenant && $domain->tenant->status === 'active') {
            $request->attributes->set('tenant', $domain->tenant);
        } elseif (config('tenancy.enforce_known_domains') && ! in_array($host, $platformHosts, true)) {
            abort(404);
        }

        return $next($request);
    }
}
