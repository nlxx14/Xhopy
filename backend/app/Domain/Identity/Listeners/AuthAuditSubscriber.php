<?php

namespace App\Domain\Identity\Listeners;

use App\Domain\Identity\Enums\AuditEvent;
use App\Domain\Identity\Services\AccountAuditRecorder;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

final class AuthAuditSubscriber
{
    public function __construct(private readonly AccountAuditRecorder $audit) {}

    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record(AuditEvent::Login, $event->user, ['guard' => $event->guard]);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->audit->record(AuditEvent::LoginFailed, $user, ['guard' => $event->guard]);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record(AuditEvent::Logout, $event->user, ['guard' => $event->guard]);
        }
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record(AuditEvent::PasswordReset, $event->user);
        }
    }

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->audit->record(AuditEvent::TwoFactorEnabled, $event->user);
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->audit->record(AuditEvent::TwoFactorDisabled, $event->user);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Failed::class => 'handleFailed',
            Logout::class => 'handleLogout',
            PasswordReset::class => 'handlePasswordReset',
            TwoFactorAuthenticationConfirmed::class => 'handleTwoFactorConfirmed',
            TwoFactorAuthenticationDisabled::class => 'handleTwoFactorDisabled',
        ];
    }
}
