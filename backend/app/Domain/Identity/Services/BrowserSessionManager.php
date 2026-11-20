<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Enums\AuditEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Inspects and revokes active browser sessions for an authenticated user.
 */
final class BrowserSessionManager
{
    public function __construct(private readonly AccountAuditRecorder $audit) {}

    /**
     * @return list<array{id: string, ipAddress: string|null, device: string, isCurrent: bool, lastActiveDiff: string}>
     */
    public function sessionsFor(User $user, Request $request): array
    {
        $currentId = $request->session()->getId();

        if (config('session.driver') !== 'database') {
            return [
                [
                    'id' => $currentId,
                    'ipAddress' => $request->ip(),
                    'device' => $this->describeUserAgent($request->userAgent()),
                    'isCurrent' => true,
                    'lastActiveDiff' => 'Active now',
                ],
            ];
        }

        $table = (string) config('session.table', 'sessions');
        $rows = DB::table($table)
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get();

        return array_values(
            $rows->map(fn (object $row): array => [
                'id' => (string) $row->id,
                'ipAddress' => isset($row->ip_address) ? (string) $row->ip_address : null,
                'device' => $this->describeUserAgent(isset($row->user_agent) ? (string) $row->user_agent : null),
                'isCurrent' => (string) $row->id === $currentId,
                'lastActiveDiff' => (string) $row->id === $currentId
                    ? 'Active now'
                    : Carbon::createFromTimestamp((int) $row->last_activity)->diffForHumans(),
            ])->all(),
        );
    }

    /**
     * Revoke all other sessions belonging to the user.
     */
    public function revokeOtherSessions(User $user, Request $request): int
    {
        $revoked = 0;

        if (config('session.driver') === 'database') {
            $table = (string) config('session.table', 'sessions');
            $revoked = DB::table($table)
                ->where('user_id', $user->getKey())
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        $this->audit->record(
            AuditEvent::OtherSessionsRevoked,
            $user,
            ['revoked_count' => $revoked],
            $user,
            $request,
        );

        return $revoked;
    }

    private function describeUserAgent(?string $userAgent): string
    {
        if ($userAgent === null || $userAgent === '') {
            return 'Unknown browser';
        }

        $platform = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') || str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Device',
        };

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        return "{$browser} on {$platform}";
    }
}
