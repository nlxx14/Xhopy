<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Services\AdminAuditRecorder;
use App\Domain\Catalog\Models\Product;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DeleteProductsAction
{
    public const MAX_BATCH_SIZE = 100;

    public function __construct(
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * Safely delete one or more products after verifying none are tied to in-flight unfulfilled orders.
     *
     * @param  list<int>  $productIds
     */
    public function execute(User $actor, array $productIds): int
    {
        $uniqueIds = array_values(array_unique($productIds));

        if ($uniqueIds === []) {
            return 0;
        }

        // Prevent deleting products that are inside active in-flight orders
        $activeOrderProductId = OrderItem::query()
            ->whereIn('product_id', $uniqueIds)
            ->whereIn(
                'order_id',
                Order::query()
                    ->whereIn('status', [
                        OrderStatus::PendingPayment,
                        OrderStatus::Paid,
                        OrderStatus::Processing,
                        OrderStatus::Shipped,
                    ])
                    ->select('id'),
            )
            ->value('product_id');

        if ($activeOrderProductId !== null) {
            $blockedProduct = Product::query()->find((int) $activeOrderProductId);
            $label = $blockedProduct !== null
                ? "{$blockedProduct->name} ({$blockedProduct->sku})"
                : "#{$activeOrderProductId}";

            throw ValidationException::withMessages([
                'product_ids' => __(
                    'Cannot delete :product while it belongs to an active unfulfilled order. Set it to Archived or complete/cancel the order first.',
                    ['product' => $label],
                ),
            ]);
        }

        return DB::transaction(function () use ($actor, $uniqueIds): int {
            $products = Product::query()
                ->whereIn('id', $uniqueIds)
                ->with('images')
                ->lockForUpdate()
                ->get();

            if ($products->isEmpty()) {
                return 0;
            }

            $storedFilesToDelete = [];
            $deletedSummaries = [];

            foreach ($products as $product) {
                foreach ($product->images as $image) {
                    if ($image->path !== '' && ! str_starts_with($image->path, 'https://')) {
                        $storedFilesToDelete[] = $image->path;
                    }
                }

                $deletedSummaries[] = [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                ];

                $product->delete();
            }

            foreach (array_unique($storedFilesToDelete) as $filePath) {
                Storage::disk('public')->delete($filePath);
            }

            $count = count($deletedSummaries);

            if ($count === 1) {
                $item = $deletedSummaries[0];
                $this->audit->record(
                    $actor,
                    'product.deleted',
                    sprintf('Deleted product "%s" (%s).', $item['name'], $item['sku']),
                    'Product',
                    $item['id'],
                    $item,
                );
            } else {
                $this->audit->record(
                    $actor,
                    'product.bulk_deleted',
                    sprintf('Bulk-deleted %d products.', $count),
                    'Product',
                    null,
                    [
                        'count' => $count,
                        'products' => $deletedSummaries,
                    ],
                );
            }

            return $count;
        });
    }
}
