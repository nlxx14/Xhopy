<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\AdminAuditLog;
use App\Models\User;

final class AdminAuditRecorder
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        User $actor,
        string $action,
        string $summary,
        ?string $targetType = null,
        ?int $targetId = null,
        ?array $metadata = null,
    ): AdminAuditLog {
        $activeRequest = app()->bound('request') ? request() : null;

        return AdminAuditLog::query()->create([
            'actor_id' => $actor->id,
            'actor_role' => $actor->role->value,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'summary' => mb_substr($summary, 0, 255),
            'metadata' => $metadata,
            'ip_address' => $activeRequest?->ip(),
            'occurred_at' => now(),
        ]);
    }
}
