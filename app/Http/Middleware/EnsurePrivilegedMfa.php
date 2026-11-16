<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePrivilegedMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->hasAnyRole(['super-admin', 'operations-admin', 'finance-admin', 'kyc-reviewer']) && ! $request->session()->boolean('mfa_verified')) {
            return redirect()->route($user->mfa_confirmed_at ? 'mfa.challenge' : 'mfa.setup');
        }
        return $next($request);
    }
}
