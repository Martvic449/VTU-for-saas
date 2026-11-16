<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PasswordResetController
{
    public function requestForm() { return view('auth.password-email'); }
    public function resetForm(Request $request) { return view('auth.password-reset', ['token' => $request->query('token'), 'email' => $request->query('email')]); }

    public function send(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        // Always return the same response to avoid revealing whether an account exists.
        Password::sendResetLink(['email' => $request->string('email')->toString()]);
        return back()->with('status', 'If an account exists for that address, a password reset link has been sent.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'string', 'min:12', 'confirmed']]);
        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'failed_login_attempts' => 0, 'locked_until' => null])->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) throw ValidationException::withMessages(['email' => __($status)]);
        return redirect()->route('login')->with('status', 'Password reset successfully. Sign in with your new password.');
    }
}
