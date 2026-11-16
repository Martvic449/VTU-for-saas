<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    protected $fillable = ['event_uuid', 'event_type', 'aggregate_type', 'aggregate_id', 'payload', 'status', 'attempts', 'available_at', 'processed_at'];
    protected function casts(): array { return ['payload' => 'array', 'available_at' => 'datetime', 'processed_at' => 'datetime']; }
}
