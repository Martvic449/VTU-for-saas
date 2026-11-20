<?php

namespace App\\Http\\Controllers;

use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Validation\\Rule;

final class ProfileController
{
    public function edit(Request $request)
    {
        return view('account.profile', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->getKey())],
            'current_password' => ['required', 'current_password'],
        ]);

        if (strcasecmp($user->email, $data['email']) === 0) {
            if (! $user->hasVerifiedEmail()) {
                $user->sendEmailVerificationNotification();

                return redirect()->route('verification.notice')->with('status', 'A fresh verification link has been sent to your email address.');
            }

            return back()->with('status', 'Your email address is already up to date and verified.');
        }

        $user->forceFill([
            'email' => $data['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();

        return redirect()->route('verification.notice')->with('status', 'Your email address has been changed. Check your new inbox and verify the address before continuing to use verified account features.');
    }
}
