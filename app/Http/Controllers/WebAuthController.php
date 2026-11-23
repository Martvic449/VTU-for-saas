<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TotpService;
use App\Services\Accounting\WalletProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class WebAuthController
{
    public function showLogin() { return view('auth.login'); }
    public function showRegister() { return view('auth.register'); }

    public function register(Request $request, WalletProvisioningService $wallets): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $user = User::create($data + ['status' => 'active']);
        $user->assignRole('customer');
        $wallets->create('user', (int) $user->id);
        Auth::login($user);
        $request->session()->regenerate();
        $user->sendEmailVerificationNotification();
        return redirect()->route('verification.notice');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = 'web-login:'.strtolower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) throw ValidationException::withMessages(['email' => 'Too many sign-in attempts. Try again in a minute.']);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password) || $user->status !== 'active') {
            RateLimiter::hit($key, 60);
            if ($user && $user->status === 'active') {
                $failures = min(255, (int) $user->failed_login_attempts + 1);
                $user->forceFill(['failed_login_attempts' => $failures, 'locked_until' => $failures >= 10 ? now()->addMinutes(15) : $user->locked_until])->save();
            }
            throw ValidationException::withMessages(['email' => 'The supplied credentials are invalid.']);
        }
        if ($user->locked_until && $user->locked_until->isFuture()) throw ValidationException::withMessages(['email' => 'The supplied credentials are invalid.']);
        RateLimiter::clear($key);
        if (! $user->hasVerifiedEmail()) {
            Auth::login($user);
            $request->session()->regenerate();
            $user->sendEmailVerificationNotification();
            return redirect()->route('verification.notice');
        }
        if ($user->hasAnyRole(['super-admin', 'operations-admin', 'finance-admin', 'kyc-reviewer'])) {
            // Rotate the pre-authentication session before storing the MFA challenge state.
            $request->session()->regenerate();
            $request->session()->put('pending_mfa_user_id', $user->id);
            return $user->mfa_confirmed_at ? redirect()->route('mfa.challenge') : redirect()->route('mfa.setup');
        }
        $user->forceFill(['last_login_at' => now(), 'failed_login_attempts' => 0, 'locked_until' => null])->save();
        Auth::login($user);
        $request->session()->regenerate();
        $tenant = $request->attributes->get('tenant');
        if ($user->hasRole('tenant-owner')) {
            return $tenant
                ? redirect()->route('tenant.dashboard')
                : redirect()->route('reseller.dashboard');
        }
        if ($tenant) {
            if (\Illuminate\Support\Facades\DB::table('tenant_customers')->where('tenant_id', $tenant->id)->where('user_id', $user->id)->where('status', 'active')->exists()) return redirect()->route('tenant.customer.dashboard');
            return redirect()->route('home');
        }
        $hasDirectWallet = \Illuminate\Support\Facades\DB::table('wallets')->where('owner_type','user')->where('owner_id',$user->id)->whereNull('tenant_id')->exists();
        if (! $hasDirectWallet) {
            $customerDomain = \Illuminate\Support\Facades\DB::table('tenant_customers')->join('tenants','tenants.id','=','tenant_customers.tenant_id')
                ->join('tenant_domains','tenant_domains.tenant_id','=','tenants.id')->where('tenant_customers.user_id',$user->id)
                ->where('tenant_customers.status','active')->where('tenants.status','active')->whereNotNull('tenant_domains.verified_at')
                ->orderByDesc('tenant_customers.created_at')->select('tenant_domains.hostname')->first();
            if ($customerDomain) return redirect()->away('https://'.$customerDomain->hostname.'/customer/dashboard');
        }
        return redirect()->route('dashboard');
    }

    public function showChallenge() { abort_unless(session()->has('pending_mfa_user_id'), 403); return view('auth.mfa-challenge'); }

    public function challenge(Request $request, TotpService $totp): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = User::findOrFail((int) $request->session()->get('pending_mfa_user_id'));
        if (! $user->mfa_confirmed_at || ! $totp->verify((string) $user->mfa_secret_encrypted, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'The authenticator code is invalid.']);
        }
        $user->forceFill(['last_login_at' => now(), 'failed_login_attempts' => 0, 'locked_until' => null])->save();
        Auth::login($user);
        // Rotate the session after authentication, then write the MFA marker to the new session.
        $request->session()->forget('pending_mfa_user_id');
        $request->session()->regenerate();
        $request->session()->put('mfa_verified', true);
        return redirect()->route('platform.dashboard');
    }

    public function showSetup(Request $request, TotpService $totp)
    {
        $userId = $request->session()->get('pending_mfa_user_id');
        $user = $request->user() ?: ($userId ? User::findOrFail($userId) : null);
        abort_unless($user && $user->hasAnyRole(['super-admin', 'operations-admin', 'finance-admin', 'kyc-reviewer']), 403);
        if (! $user->mfa_secret_encrypted) {
            $secret = $totp->generateSecret();
            $user->forceFill(['mfa_secret_encrypted' => $secret])->save();
        } else $secret = (string) $user->mfa_secret_encrypted;
        return view('auth.mfa-setup', ['secret' => $secret, 'uri' => $totp->uri($secret, $user->email, config('app.name'))]);
    }

    public function confirmSetup(Request $request, TotpService $totp): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();
        if (! $user) $user = User::findOrFail((int) $request->session()->get('pending_mfa_user_id'));
        if (! $user->mfa_secret_encrypted || ! $totp->verify((string) $user->mfa_secret_encrypted, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'The authenticator code is invalid.']);
        }
        $user->forceFill(['mfa_confirmed_at' => now()])->save();
        $user->forceFill(['last_login_at' => now(), 'failed_login_attempts' => 0, 'locked_until' => null])->save();
        if (! Auth::check()) Auth::login($user);
        // Rotate the session after authentication, then write the MFA marker to the new session.
        $request->session()->forget('pending_mfa_user_id');
        $request->session()->regenerate();
        $request->session()->put('mfa_verified', true);
        return redirect()->route('platform.dashboard')->with('status', 'Multi-factor authentication enabled.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
