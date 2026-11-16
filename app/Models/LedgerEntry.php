<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    public $timestamps = false;
    protected $fillable = ['ledger_journal_id', 'ledger_account_id', 'side', 'amount_minor', 'currency', 'memo', 'created_at'];
    protected function casts(): array { return ['amount_minor' => 'integer', 'created_at' => 'datetime']; }
    public function journal(): BelongsTo { return $this->belongsTo(LedgerJournal::class, 'ledger_journal_id'); }
    public function account(): BelongsTo { return $this->belongsTo(LedgerAccount::class, 'ledger_account_id'); }
}
