<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Auditable inventory movement ledger and reconciliation service (database-standards.md).
 */
final class InventoryService
{
    /**
     * Apply a signed stock delta inside a row-locked transaction and record a StockMovement ledger entry.
     */
    public function adjustStock(
        Product $product,
        int $delta,
        StockMovementType $type,
        ?User $actor = null,
        ?string $reason = null,
        ?Order $order = null,
    ): StockMovement {
        if ($delta === 0) {
            throw ValidationException::withMessages([
                'quantity_delta' => __('Stock adjustment delta cannot be zero.'),
            ]);
        }

        return DB::transaction(function () use ($product, $delta, $type, $actor, $reason, $order): StockMovement {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $previous = $locked->stock_quantity;
            $resulting = $previous + $delta;

            if ($resulting < 0) {
                throw ValidationException::withMessages([
                    'quantity_delta' => __('Insufficient stock for :name (current stock is :current).', [
                        'name' => $locked->name,
                        'current' => $previous,
                    ]),
                ]);
            }

            $locked->stock_quantity = $resulting;
            $locked->save();

            return StockMovement::query()->create([
                'product_id' => $locked->id,
                'order_id' => $order?->id,
                'actor_id' => $actor?->id,
                'type' => $type,
                'quantity_delta' => $delta,
                'previous_quantity' => $previous,
                'resulting_quantity' => $resulting,
                'reason' => $reason,
                'occurred_at' => now(),
            ]);
        });
    }

    /**
     * Record a stock movement when the caller has already locked and mutated the Product row inside a transaction.
     */
    public function recordMovementForLockedProduct(
        Product $lockedProduct,
        int $previousQuantity,
        int $resultingQuantity,
        StockMovementType $type,
        ?User $actor = null,
        ?string $reason = null,
        ?Order $order = null,
    ): StockMovement {
        return StockMovement::query()->create([
            'product_id' => $lockedProduct->id,
            'order_id' => $order?->id,
            'actor_id' => $actor?->id,
            'type' => $type,
            'quantity_delta' => $resultingQuantity - $previousQuantity,
            'previous_quantity' => $previousQuantity,
            'resulting_quantity' => $resultingQuantity,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Reconcile a product's mutable `stock_quantity` against the sum of its `stock_movements` ledger.
     * If a baseline or discrepancy exists, writes a `reconciliation` ledger movement so the ledger balances.
     *
     * @return array{
     *     product_id: int,
     *     sku: string,
     *     recorded_stock: int,
     *     ledger_sum: int,
     *     discrepancy: int,
     *     reconciled: bool
     * }
     */
    public function reconcileProduct(Product $product, ?User $actor = null): array
    {
        return DB::transaction(function () use ($product, $actor): array {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $ledgerSum = (int) StockMovement::query()
                ->where('product_id', $locked->id)
                ->sum('quantity_delta');

            $recordedStock = $locked->stock_quantity;
            $discrepancy = $recordedStock - $ledgerSum;

            if ($discrepancy !== 0) {
                StockMovement::query()->create([
                    'product_id' => $locked->id,
                    'order_id' => null,
                    'actor_id' => $actor?->id,
                    'type' => StockMovementType::Reconciliation,
                    'quantity_delta' => $discrepancy,
                    'previous_quantity' => max(0, $ledgerSum),
                    'resulting_quantity' => $recordedStock,
                    'reason' => 'Automated ledger reconciliation to match verified physical stock.',
                    'occurred_at' => now(),
                ]);
            }

            return [
                'product_id' => $locked->id,
                'sku' => $locked->sku,
                'recorded_stock' => $recordedStock,
                'ledger_sum' => $recordedStock,
                'discrepancy' => $discrepancy,
                'reconciled' => $discrepancy !== 0,
            ];
        });
    }

    /**
     * Reconcile all products in the catalog and return the summary count of reconciled items.
     */
    public function reconcileAll(?User $actor = null): int
    {
        $reconciledCount = 0;

        foreach (Product::query()->orderBy('id')->get() as $product) {
            $result = $this->reconcileProduct($product, $actor);
            if ($result['reconciled']) {
                $reconciledCount++;
            }
        }

        return $reconciledCount;
    }
}
