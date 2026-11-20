<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Services\AdminAuditRecorder;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Safe, bounded bulk product status update (admin-only).
 */
final class BulkUpdateProductStatusAction
{
    public const MAX_BATCH_SIZE = 50;

    public function __construct(private readonly AdminAuditRecorder $audit) {}

    /**
     * @param  list<int>  $productIds
     */
    public function execute(User $actor, array $productIds, ProductStatus $status): int
    {
        return DB::transaction(function () use ($actor, $productIds, $status): int {
            $uniqueIds = array_values(array_unique(array_map('intval', $productIds)));

            $products = Product::query()
                ->whereIn('id', $uniqueIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($products as $product) {
                $product->status = $status;
                if ($status === ProductStatus::Active && $product->published_at === null) {
                    $product->published_at = now();
                }
                $product->save();
            }

            $count = $products->count();

            $this->audit->record(
                $actor,
                'product.bulk_status_updated',
                sprintf('Bulk updated %d product(s) to "%s".', $count, $status->value),
                'Product',
                null,
                [
                    'status' => $status->value,
                    'count' => $count,
                    'product_ids' => $products->pluck('id')->all(),
                ],
            );

            return $count;
        });
    }
}
