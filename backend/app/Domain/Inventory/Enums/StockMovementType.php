<?php

namespace App\Domain\Inventory\Enums;

enum StockMovementType: string
{
    case InitialStock = 'initial_stock';
    case Restock = 'restock';
    case ManualAdjustment = 'manual_adjustment';
    case OrderReservation = 'order_reservation';
    case OrderCancellationRelease = 'order_cancellation_release';
    case Reconciliation = 'reconciliation';

    public function label(): string
    {
        return match ($this) {
            self::InitialStock => 'Initial Stock',
            self::Restock => 'Restock',
            self::ManualAdjustment => 'Manual Adjustment',
            self::OrderReservation => 'Order Reservation',
            self::OrderCancellationRelease => 'Order Cancellation Release',
            self::Reconciliation => 'Inventory Reconciliation',
        };
    }
}
