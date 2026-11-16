<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    protected $fillable = ['tenant_id', 'service_catalogue_id', 'cost_minor', 'sell_minor', 'floor_minor', 'ceiling_minor', 'currency', 'effective_from', 'effective_until', 'status'];
    protected function casts(): array { return ['cost_minor' => 'integer', 'sell_minor' => 'integer', 'floor_minor' => 'integer', 'ceiling_minor' => 'integer', 'effective_from' => 'datetime', 'effective_until' => 'datetime']; }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function service(): BelongsTo { return $this->belongsTo(ServiceCatalogue::class, 'service_catalogue_id'); }
}
