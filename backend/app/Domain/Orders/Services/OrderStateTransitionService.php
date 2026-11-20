<?php

namespace App\Domain\Orders\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Enforces valid order state transitions, appends audit history, and restores
 * reserved stock when an order is cancelled before shipment.
 */
final class OrderStateTransitionService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function transition(
        Order $order,
        OrderStatus $targetStatus,
        ?User $actor = null,
        ?string $note = null,
    ): Order {
        return DB::transaction(function () use ($order, $targetStatus, $actor, $note): Order {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $current = $lockedOrder->status;

            if (! $current->canTransitionTo($targetStatus)) {
                throw new InvalidArgumentException(
                    sprintf('Cannot transition order [%s] from [%s] to [%s].', $lockedOrder->order_number, $current->value, $targetStatus->value),
                );
            }

            if ($targetStatus === OrderStatus::Cancelled) {
                $this->restoreReservedStock($lockedOrder, $actor);
                $lockedOrder->cancelled_at = now();
            }

            if ($targetStatus === OrderStatus::Paid && $lockedOrder->paid_at === null) {
                $lockedOrder->paid_at = now();
            }

            $lockedOrder->status = $targetStatus;
            $lockedOrder->save();

            OrderStatusHistory::query()->create([
                'order_id' => $lockedOrder->id,
                'from_status' => $current->value,
                'to_status' => $targetStatus->value,
                'actor_id' => $actor?->id,
                'note' => $note,
                'occurred_at' => now(),
            ]);

            return $lockedOrder;
        });
    }

    private function restoreReservedStock(Order $order, ?User $actor = null): void
    {
        $order->load('items');

        $productQuantities = [];
        foreach ($order->items as $item) {
            if ($item->product_id !== null) {
                $productQuantities[$item->product_id] = ($productQuantities[$item->product_id] ?? 0) + $item->quantity;
            }
        }

        ksort($productQuantities);

        foreach ($productQuantities as $productId => $quantity) {
            $product = Product::query()
                ->whereKey($productId)
                ->lockForUpdate()
                ->first();

            if ($product === null) {
                continue;
            }

            $previous = $product->stock_quantity;
            $product->stock_quantity = $previous + $quantity;
            $product->save();

            $this->inventory->recordMovementForLockedProduct(
                $product,
                $previous,
                $product->stock_quantity,
                StockMovementType::OrderCancellationRelease,
                $actor,
                "Released from cancelled order {$order->order_number}",
                $order,
            );
        }
    }
}
