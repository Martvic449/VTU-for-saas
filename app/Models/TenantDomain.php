<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDomain extends Model
{
    protected $fillable = ['tenant_id', 'hostname', 'is_primary', 'verified_at', 'verification_token_hash', 'verification_expires_at', 'tls_status'];
    protected function casts(): array { return ['is_primary' => 'boolean', 'verified_at' => 'datetime', 'verification_expires_at' => 'datetime']; }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
