<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deposit extends Model
{
    protected $fillable = ['wallet_id', 'reference', 'gateway', 'gateway_reference', 'amount_minor', 'currency', 'status', 'verification_metadata', 'ledger_journal_id', 'verified_at'];
    protected function casts(): array { return ['amount_minor' => 'integer', 'verification_metadata' => 'array', 'verified_at' => 'datetime']; }
    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function journal(): BelongsTo { return $this->belongsTo(LedgerJournal::class, 'ledger_journal_id'); }
}
