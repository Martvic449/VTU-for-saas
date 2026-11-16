<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletLedgerAccount extends Model
{
    protected $fillable = ['wallet_id', 'ledger_account_id'];
    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function ledgerAccount(): BelongsTo { return $this->belongsTo(LedgerAccount::class); }
}
