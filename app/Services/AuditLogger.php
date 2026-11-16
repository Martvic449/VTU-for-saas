<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    public function record(string $event, ?int $tenantId = null, ?string $subjectType = null, ?string $subjectId = null, ?string $reason = null, array $metadata = []): AuditLog
    {
        $request = app(Request::class);
        return AuditLog::create([
            'tenant_id' => $tenantId,
            'actor_user_id' => $request->user()?->id,
            'event' => $event,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
