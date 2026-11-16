<?php

namespace App\Http\Middleware;

use App\Services\Developers\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\RateLimiter;

final class AuthenticateDeveloperApiKey
{
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $presented = (string) $request->header('X-API-Key', '');
        $key = app(ApiKeyService::class)->verify($presented);
        abort_unless($key, 401, 'Invalid API key.');
        abort_unless($scope === null || in_array($scope, $key->scopes, true), 403, 'API key lacks the required scope.');
        $client = \Illuminate\Support\Facades\DB::table('api_clients')->where('id', $key->api_client_id)->where('status', 'active')->first();
        abort_unless($client, 401, 'API client is inactive.');
        $clientScopes = json_decode($client->scopes ?: '[]', true) ?: [];
        abort_unless($scope === null || in_array($scope, $clientScopes, true), 403, 'API client lacks the required scope.');
        $owner = DB::table('users')->where('id', $client->user_id)->where('status', 'active')->exists();
        abort_unless($owner, 401, 'API client owner account is inactive.');
        if ($client->tenant_id) {
            $tenantActive = DB::table('tenants')->where('id', $client->tenant_id)->where('status', 'active')->exists();
            abort_unless($tenantActive, 403, 'Tenant is suspended or inactive.');
        }
        if ($client->ip_allowlist) {
            $allowlist = json_decode($client->ip_allowlist, true) ?: [];
            abort_unless(in_array($request->ip(), $allowlist, true), 403, 'Source IP is not allowed.');
        }
        $rateKey = 'developer-api-key:'.$key->id;
        if (RateLimiter::tooManyAttempts($rateKey, 60)) {
            return response()->json(['error' => ['code' => 'rate_limit_exceeded', 'message' => 'API key request limit exceeded.']], 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($rateKey));
        }
        RateLimiter::hit($rateKey, 60);
        $request->attributes->set('api_client', $client);
        $request->attributes->set('api_key', $key);
        $requestId = (string) Str::uuid();
        $started = microtime(true);
        $response = $next($request);
        DB::table('api_requests')->insert([
            'api_client_id' => $client->id, 'api_key_id' => $key->id, 'request_id' => $requestId,
            'method' => $request->method(), 'path' => '/'.$request->path(), 'status_code' => $response->getStatusCode(),
            'duration_ms' => (int) ((microtime(true) - $started) * 1000), 'ip_address' => $request->ip(), 'created_at' => now(),
        ]);
        $response->headers->set('X-Request-ID', $requestId);
        return $response;
    }
}
