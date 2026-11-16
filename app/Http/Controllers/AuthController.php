<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\TotpService;
use App\Services\Accounting\WalletProvisioningService;
use Illuminate\Validation\ValidationException;

final class AuthController
{
    public function register(Request $request, WalletProvisioningService $wallets): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
        ]);
        $user = User::create($data + ['status' => 'active']);
        $user->assignRole('customer');
        $wallets->create('user', (int) $user->id);
        $user->sendEmailVerificationNotification();
        return response()->json(['data' => ['user' => $user->only(['id', 'name', 'email', 'status']), 'verification_required' => true], 'message' => 'Account created. Verify your email before signing in.'], 202);
    }

    public function login(Request $request, TotpService $totp): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'mfa_code' => ['nullable', 'digits:6']]);
        $key = 'login:'.strtolower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many login attempts. Try again later.']);
        }
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password) || $user->status !== 'active') {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'The supplied credentials are invalid.']);
        }
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
            throw ValidationException::withMessages(['email' => 'The supplied credentials are invalid or email verification is still required. If verification is pending, a new link has been sent.']);
        }
        if ($user->locked_until && $user->locked_until->isFuture()) {
            throw ValidationException::withMessages(['email' => 'This account is temporarily locked.']);
        }
        $mfaKey = 'mfa-login:'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($mfaKey, 5)) throw ValidationException::withMessages(['mfa_code' => 'Too many authenticator attempts. Try again in a minute.']);
        if ($user->mfa_confirmed_at && (! isset($data['mfa_code']) || ! $totp->verify((string) $user->mfa_secret_encrypted, $data['mfa_code']))) {
            RateLimiter::hit($key, 60);
            RateLimiter::hit($mfaKey, 60);
            throw ValidationException::withMessages(['mfa_code' => 'A valid authenticator code is required.']);
        }
        RateLimiter::clear($key);
        RateLimiter::clear('mfa-login:'.$user->id.'|'.$request->ip());
        $user->forceFill(['last_login_at' => now(), 'failed_login_attempts' => 0, 'locked_until' => null])->save();
        $privileged = $user->hasAnyRole(['super-admin', 'operations-admin', 'finance-admin', 'kyc-reviewer']);
        if ($privileged && ! $user->mfa_confirmed_at) {
            $token = $user->createToken('mfa-setup', ['mfa:setup'])->plainTextToken;
            return response()->json(['data' => ['user' => $user->only(['id', 'name', 'email', 'status']), 'token' => $token, 'mfa_setup_required' => true]]);
        }
        $abilities = $privileged ? ['*', 'mfa:verified'] : ['*'];
        $token = $user->createToken('api-session', $abilities)->plainTextToken;
        return response()->json(['data' => ['user' => $user->only(['id', 'name', 'email', 'status']), 'token' => $token, 'mfa_setup_required' => false]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['message' => 'Signed out.']);
    }

    public function setupMfa(Request $request, TotpService $totp): JsonResponse
    {
        $user = $request->user();
        abort_if($user->mfa_confirmed_at, 409, 'MFA is already enabled.');
        $secret = $totp->generateSecret();
        $user->forceFill(['mfa_secret_encrypted' => $secret])->save();
        return response()->json(['data' => ['secret' => $secret, 'otpauth_uri' => $totp->uri($secret, $user->email, config('app.name'))]]);
    }

    public function confirmMfa(Request $request, TotpService $totp): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();
        abort_unless($user->mfa_secret_encrypted, 409, 'Start MFA setup first.');
        if (! $totp->verify((string) $user->mfa_secret_encrypted, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'The authenticator code is invalid.']);
        }
        $user->forceFill(['mfa_confirmed_at' => now()])->save();
        $request->user()->currentAccessToken()?->delete();
        $token = $user->createToken('api-session', ['*', 'mfa:verified'])->plainTextToken;
        return response()->json(['message' => 'Multi-factor authentication enabled.', 'data' => ['token' => $token]]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->only(['id', 'name', 'email', 'phone', 'status', 'last_login_at'])]);
    }
}
