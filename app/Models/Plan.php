<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['code', 'name', 'price_minor', 'currency', 'billing_interval', 'entitlements', 'status'];
    protected function casts(): array { return ['price_minor' => 'integer', 'entitlements' => 'array']; }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }
}
