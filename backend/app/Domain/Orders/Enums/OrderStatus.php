<?php

namespace App\Domain\Orders\Enums;

/**
 * Explicit order lifecycle states and allowed transitions (backend-standards.md rule: reject invalid transitions).
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending Payment',
            self::Paid => 'Paid',
            self::Processing => 'Processing',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return false;
        }

        return match ($this) {
            self::PendingPayment => in_array($next, [self::Paid, self::Processing, self::Cancelled], true),
            self::Paid => in_array($next, [self::Processing, self::Cancelled], true),
            self::Processing => in_array($next, [self::Shipped, self::Cancelled], true),
            self::Shipped => $next === self::Delivered,
            self::Delivered, self::Cancelled => false,
        };
    }

    public function canBeCancelledByCustomer(): bool
    {
        return $this === self::PendingPayment;
    }
}
