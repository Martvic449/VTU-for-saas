<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionAttempt extends Model
{
    protected $fillable = ['transaction_id', 'provider_id', 'attempt_number', 'status', 'provider_reference', 'request_redacted', 'response_redacted', 'error_message', 'started_at', 'finished_at'];
    protected function casts(): array { return ['request_redacted' => 'array', 'response_redacted' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime']; }
    public function transaction(): BelongsTo { return $this->belongsTo(VtuTransaction::class, 'transaction_id'); }
    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
}
