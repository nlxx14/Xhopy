<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Identity\Enums\AuditEvent;
use App\Domain\Identity\Models\AccountAuditLog;
use App\Domain\Identity\Services\AccountAuditRecorder;
use App\Domain\Identity\Services\BrowserSessionManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class SecurityController extends Controller
{
    private const RECENT_AUDIT_LIMIT = 10;

    /**
     * Show the user's security settings page.
     */
    public function edit(
        TwoFactorAuthenticationRequest $request,
        BrowserSessionManager $sessions,
    ): Response {
        $user = $request->user();

        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => Features::canManagePasskeys()
                ? $user
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey) => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'sessions' => $sessions->sessionsFor($user, $request),
            'recentSecurityEvents' => array_values(
                $user->auditLogs()
                    ->limit(self::RECENT_AUDIT_LIMIT)
                    ->get()
                    ->map(fn (AccountAuditLog $log): array => [
                        'id' => $log->id,
                        'event' => $log->event->value,
                        'label' => $log->event->label(),
                        'ipAddress' => $log->ip_address,
                        'occurredAtDiff' => $log->occurred_at->diffForHumans(),
                    ])
                    ->all(),
            ),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $user->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/Security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(
        PasswordUpdateRequest $request,
        AccountAuditRecorder $audit,
    ): RedirectResponse {
        $user = $request->user();

        $user->update([
            'password' => $request->password,
        ]);

        $audit->record(AuditEvent::PasswordUpdated, $user, null, $user, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }

    /**
     * Log out and revoke all other active browser sessions.
     */
    public function destroyOtherSessions(
        Request $request,
        BrowserSessionManager $sessions,
    ): RedirectResponse {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
        ]);

        Auth::logoutOtherDevices((string) $validated['current_password']);
        $sessions->revokeOtherSessions($request->user(), $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Logged out of other browser sessions.')]);

        return back();
    }
}
