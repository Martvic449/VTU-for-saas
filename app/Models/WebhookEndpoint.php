<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    protected $fillable = ['tenant_id', 'api_client_id', 'url', 'secret_encrypted', 'events', 'status'];
    protected $hidden = ['secret_encrypted'];
    protected function casts(): array { return ['secret_encrypted' => 'encrypted', 'events' => 'array']; }
    public function deliveries(): HasMany { return $this->hasMany(WebhookDelivery::class); }
}
