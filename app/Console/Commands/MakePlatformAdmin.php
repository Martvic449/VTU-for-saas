<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

final class MakePlatformAdmin extends Command
{
    protected $signature = 'platform:make-admin {email} {name}';
    protected $description = 'Create or promote a platform super administrator (run only from a trusted CLI).';

    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $user = User::firstOrNew(['email' => $email]);
        if (! $user->exists) {
            $password = $this->secret('Temporary password (minimum 12 characters)');
            if (! is_string($password) || strlen($password) < 12) {
                $this->error('Password must contain at least 12 characters.');
                return self::FAILURE;
            }
            $user->name = (string) $this->argument('name');
            $user->password = $password;
            $user->status = 'active';
            $user->email_verified_at = now();
            $user->save();
        }
        $user->assignRole('super-admin');
        $this->info('Super administrator assigned. MFA must be configured at first sign-in.');
        return self::SUCCESS;
    }
}
