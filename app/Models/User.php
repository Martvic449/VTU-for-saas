<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, MustVerifyEmailTrait;
    protected $fillable = ['name', 'email', 'phone', 'password', 'status'];
    protected $hidden = ['password', 'remember_token', 'mfa_secret_encrypted'];
    protected function casts(): array { return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'locked_until' => 'datetime', 'last_login_at' => 'datetime', 'mfa_confirmed_at' => 'datetime', 'mfa_secret_encrypted' => 'encrypted']; }
    public function wallet(): HasOne { return $this->hasOne(Wallet::class, 'owner_id')->where('owner_type', 'user'); }
    public function tenants() { return $this->belongsToMany(Tenant::class, 'tenant_user')->withPivot(['role', 'status'])->withTimestamps(); }
}
