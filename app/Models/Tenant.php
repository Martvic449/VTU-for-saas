<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'status', 'timezone', 'currency', 'settings'];
    protected function casts(): array { return ['settings' => 'array']; }
    public function domains(): HasMany { return $this->hasMany(TenantDomain::class); }
    public function wallet(): HasOne { return $this->hasOne(Wallet::class, 'owner_id')->where('owner_type', 'tenant'); }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }
    public function users(): BelongsToMany { return $this->belongsToMany(User::class, 'tenant_user')->withPivot(['role', 'status'])->withTimestamps(); }
}
