<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    protected $hidden = ['credentials_encrypted'];
    protected $fillable = ['code', 'name', 'adapter', 'environment', 'status', 'credentials_encrypted', 'settings', 'last_health_check_at'];
    protected function casts(): array { return ['credentials_encrypted' => 'encrypted:array', 'settings' => 'array', 'last_health_check_at' => 'datetime']; }
    public function services(): HasMany { return $this->hasMany(ProviderService::class); }
}
