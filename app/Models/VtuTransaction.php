<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VtuTransaction extends Model
{
    protected $table = 'transactions';
    protected $fillable = ['tenant_id', 'wallet_id', 'service_catalogue_id', 'provider_id', 'provider_service_id', 'provider_product_code', 'reference', 'idempotency_key', 'status', 'sell_minor', 'cost_minor', 'currency', 'input_snapshot', 'price_snapshot', 'provider_reference', 'ledger_journal_id', 'settlement_journal_id', 'failure_reason', 'submitted_at', 'completed_at'];
    protected function casts(): array { return ['sell_minor' => 'integer', 'cost_minor' => 'integer', 'input_snapshot' => 'array', 'price_snapshot' => 'array', 'submitted_at' => 'datetime', 'completed_at' => 'datetime']; }
    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function service(): BelongsTo { return $this->belongsTo(ServiceCatalogue::class, 'service_catalogue_id'); }
    public function attempts(): HasMany { return $this->hasMany(TransactionAttempt::class, 'transaction_id'); }
}
