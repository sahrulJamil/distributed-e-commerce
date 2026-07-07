<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogService
{
    public function log(
        ?int $userId,
        string $action,
        string $description,
        array $metadata = [],
        ?Request $request = null
    ): void {
        ActivityLog::create([
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'metadata' => [
                ...$metadata,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ],
            'created_at' => now(),
        ]);
    }
}