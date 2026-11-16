<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderService extends Model
{
    protected $fillable = ['provider_id', 'service_catalogue_id', 'provider_product_code', 'status', 'priority', 'mapping'];
    protected function casts(): array { return ['mapping' => 'array']; }
    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
    public function service(): BelongsTo { return $this->belongsTo(ServiceCatalogue::class, 'service_catalogue_id'); }
}
