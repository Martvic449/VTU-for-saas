<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 30)->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->text('mfa_secret_encrypted')->nullable();
            $table->timestamp('mfa_confirmed_at')->nullable();
            $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['phone']);
            $table->dropColumn(['phone', 'phone_verified_at', 'mfa_secret_encrypted', 'mfa_confirmed_at', 'failed_login_attempts', 'locked_until', 'last_login_at']);
        });
    }
};
