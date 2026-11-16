<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCatalogue extends Model
{
    protected $table = 'service_catalogue';
    protected $fillable = ['service_code', 'category', 'name', 'status', 'validation_schema', 'metadata'];
    protected function casts(): array { return ['validation_schema' => 'array', 'metadata' => 'array']; }
    public function pricingRules(): HasMany { return $this->hasMany(PricingRule::class); }
    public function providerServices(): HasMany { return $this->hasMany(ProviderService::class); }
}
