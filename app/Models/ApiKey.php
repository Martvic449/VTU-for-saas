<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    protected $fillable = ['api_client_id', 'name', 'prefix', 'secret_hash', 'scopes', 'last_used_at', 'expires_at', 'revoked_at'];
    protected $hidden = ['secret_hash'];
    protected function casts(): array { return ['scopes' => 'array', 'last_used_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime']; }
    public function client(): BelongsTo { return $this->belongsTo(ApiClient::class, 'api_client_id'); }
}
