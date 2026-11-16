<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerAccount extends Model
{
    protected $fillable = ['code', 'name', 'account_type', 'normal_side', 'currency', 'is_control_account'];
    protected function casts(): array { return ['is_control_account' => 'boolean']; }
    public function entries(): HasMany { return $this->hasMany(LedgerEntry::class); }
}
