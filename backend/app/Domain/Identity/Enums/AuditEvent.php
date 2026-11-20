<?php

namespace App\Domain\Identity\Enums;

enum AuditEvent: string
{
    case Login = 'auth.login';
    case LoginFailed = 'auth.login_failed';
    case Logout = 'auth.logout';
    case PasswordReset = 'auth.password_reset';
    case ProfileUpdated = 'account.profile_updated';
    case EmailChanged = 'account.email_changed';
    case EmailVerified = 'account.email_verified';
    case PasswordUpdated = 'account.password_updated';
    case TwoFactorEnabled = 'account.two_factor_enabled';
    case TwoFactorDisabled = 'account.two_factor_disabled';
    case OtherSessionsRevoked = 'account.other_sessions_revoked';
    case RoleChanged = 'account.role_changed';
    case AddressCreated = 'account.address_created';
    case AddressUpdated = 'account.address_updated';
    case AddressDeleted = 'account.address_deleted';
    case AccountDeleted = 'account.deleted';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Signed in',
            self::LoginFailed => 'Failed sign-in attempt',
            self::Logout => 'Signed out',
            self::PasswordReset => 'Password reset via recovery link',
            self::ProfileUpdated => 'Profile information updated',
            self::EmailChanged => 'Account email address changed',
            self::EmailVerified => 'Email address verified',
            self::PasswordUpdated => 'Account password changed',
            self::TwoFactorEnabled => 'Two-factor authentication enabled',
            self::TwoFactorDisabled => 'Two-factor authentication disabled',
            self::OtherSessionsRevoked => 'Logged out of other browser sessions',
            self::RoleChanged => 'Account role updated',
            self::AddressCreated => 'Saved a new address',
            self::AddressUpdated => 'Updated a saved address',
            self::AddressDeleted => 'Removed a saved address',
            self::AccountDeleted => 'Account deleted',
        };
    }
}
