<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Wallet extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'owner_type', 'owner_id', 'currency', 'status'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function deposits(): HasMany { return $this->hasMany(Deposit::class); }
    public function transactions(): HasMany { return $this->hasMany(VtuTransaction::class); }
    public function ledgerMapping(): HasOne { return $this->hasOne(WalletLedgerAccount::class); }
}
