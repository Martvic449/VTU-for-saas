<?php

namespace App\\Http\\Controllers;

use App\\Models\\User;
use Illuminate\\Http\\RedirectResponse;

final class EmailVerificationController
{
    public function verify(int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403, 'This email verification link is invalid.');

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->route('verification.complete');
    }
}
