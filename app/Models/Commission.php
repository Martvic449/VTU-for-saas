<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    protected $fillable = ['tenant_id', 'transaction_id', 'beneficiary_user_id', 'amount_minor', 'currency', 'status', 'calculation_version', 'reversal_of_id'];
    protected function casts(): array { return ['amount_minor' => 'integer']; }
    public function transaction(): BelongsTo { return $this->belongsTo(VtuTransaction::class, 'transaction_id'); }
    public function reversalOf(): BelongsTo { return $this->belongsTo(self::class, 'reversal_of_id'); }
}
