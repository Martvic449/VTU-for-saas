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

        if ($domain && $domain->tenant && $domain->tenant->status === 'active') {
            // Platform-owned reseller subdomains are covered by one wildcard DNS
            // record and wildcard TLS certificate. They do not need per-tenant TXT challenges.
            $platformManaged = $base !== '' && str_ends_with($host, '.'.$base);

            if ($platformManaged) {
                if (! $request->isSecure()) {
                    return redirect()->secure($request->getRequestUri(), 301);
                }

                $changes = [];
                if (! $domain->verified_at) $changes['verified_at'] = now();
                if ($domain->tls_status !== 'active') $changes['tls_status'] = 'active';
                if ($changes) $domain->forceFill($changes)->save();
            } elseif (! $domain->verified_at || $domain->tls_status !== 'active') {
                abort(404);
            }

            $request->attributes->set('tenant', $domain->tenant);
        } elseif ($domain) {
            abort(404);
        } elseif (config('tenancy.enforce_known_domains') && ! in_array($host, $platformHosts, true)) {
            abort(404);
        }

        return $next($request);
    }
}
