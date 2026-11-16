<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class KycRecord extends Model
{
    protected $fillable = ['tenant_id', 'subject_type', 'subject_id', 'status', 'provider_reference', 'evidence_metadata', 'reviewed_by', 'review_notes', 'reviewed_at'];
    protected function casts(): array { return ['evidence_metadata' => 'encrypted:array', 'reviewed_at' => 'datetime']; }
    public function subject(): MorphTo { return $this->morphTo(); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
