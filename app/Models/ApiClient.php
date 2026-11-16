<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiClient extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'name', 'environment', 'scopes', 'ip_allowlist', 'status'];
    protected function casts(): array { return ['scopes' => 'array', 'ip_allowlist' => 'array']; }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function keys(): HasMany { return $this->hasMany(ApiKey::class); }
}
