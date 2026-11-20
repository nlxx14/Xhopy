<?php

namespace App\Domain\Identity\Enums;

/**
 * Least-privilege platform roles (security-standards.md: separate customer, staff, and admin permissions).
 */
enum UserRole: string
{
    case Customer = 'customer';
    case Staff = 'staff';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Staff => 'Staff',
            self::Admin => 'Administrator',
        };
    }

    public function canAccessBackOffice(): bool
    {
        return $this === self::Staff || $this === self::Admin;
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }
}
