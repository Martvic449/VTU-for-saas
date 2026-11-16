<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerJournal extends Model
{
    protected $fillable = ['reference', 'event_type', 'source_type', 'source_id', 'currency', 'status', 'description', 'reversal_of_id', 'created_by', 'posted_at'];
    protected function casts(): array { return ['posted_at' => 'datetime']; }
    public function entries(): HasMany { return $this->hasMany(LedgerEntry::class); }
    public function reversalOf(): BelongsTo { return $this->belongsTo(self::class, 'reversal_of_id'); }
}
