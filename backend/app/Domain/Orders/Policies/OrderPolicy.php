<?php

namespace App\Domain\Orders\Policies;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Models\User;

final class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->isStaffOrAdmin();
    }

    public function cancel(User $user, Order $order): bool
    {
        if ($user->isStaffOrAdmin()) {
            return $order->status->canTransitionTo(OrderStatus::Cancelled);
        }

        return $order->user_id === $user->id && $order->status->canBeCancelledByCustomer();
    }
}
