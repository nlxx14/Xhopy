<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Enums\AuditEvent;
use App\Domain\Identity\Models\AccountAuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Records security and account audit events while stripping any sensitive keys from metadata.
 */
final class AccountAuditRecorder
{
    /**
     * Keys that must never be persisted inside audit metadata.
     *
     * @var list<string>
     */
    private const FORBIDDEN_KEYS = [
        'password',
        'current_password',
        'password_confirmation',
        'token',
        'remember_token',
        'secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'recovery_code',
        'code',
    ];

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        AuditEvent $event,
        ?User $user = null,
        ?array $metadata = null,
        ?User $actor = null,
        ?Request $request = null,
    ): AccountAuditLog {
        $activeRequest = $request ?? (app()->bound('request') ? request() : null);
        $userAgent = $activeRequest?->userAgent();

        return AccountAuditLog::query()->create([
            'user_id' => $user?->getKey(),
            'actor_id' => ($actor ?? $user)?->getKey(),
            'event' => $event,
            'ip_address' => $activeRequest?->ip(),
            'user_agent' => is_string($userAgent) && $userAgent !== '' ? mb_substr($userAgent, 0, 255) : null,
            'metadata' => $this->sanitizeMetadata($metadata),
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    private function sanitizeMetadata(?array $metadata): ?array
    {
        if ($metadata === null || $metadata === []) {
            return null;
        }

        $clean = [];
        foreach ($metadata as $key => $value) {
            if (in_array(strtolower((string) $key), self::FORBIDDEN_KEYS, true)) {
                continue;
            }

            $clean[(string) $key] = is_array($value) ? $this->sanitizeMetadata($value) : $value;
        }

        return $clean === [] ? null : $clean;
    }
}
