<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Services\AdminAuditRecorder;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Services\InventoryService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SaveProductAction
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $actor, array $attributes, ?Product $existing = null): Product
    {
        return DB::transaction(function () use ($actor, $attributes, $existing): Product {
            $name = trim((string) $attributes['name']);
            $slug = isset($attributes['slug']) && trim((string) $attributes['slug']) !== ''
                ? Str::slug((string) $attributes['slug'])
                : Str::slug($name);
            $sku = strtoupper(trim((string) $attributes['sku']));
            $status = $attributes['status'] instanceof ProductStatus
                ? $attributes['status']
                : (ProductStatus::tryFrom((string) ($attributes['status'] ?? 'draft')) ?? ProductStatus::Draft);

            $desiredStock = max(0, (int) ($attributes['stock_quantity'] ?? 0));
            $isCreating = $existing === null;

            $publishedAt = $existing?->published_at;
            if ($status === ProductStatus::Active && $publishedAt === null) {
                $publishedAt = now();
            }

            $payload = [
                'category_id' => (int) $attributes['category_id'],
                'name' => $name,
                'slug' => $slug,
                'sku' => $sku,
                'short_description' => isset($attributes['short_description']) && trim((string) $attributes['short_description']) !== ''
                    ? trim((string) $attributes['short_description'])
                    : null,
                'description' => isset($attributes['description']) && trim((string) $attributes['description']) !== ''
                    ? trim((string) $attributes['description'])
                    : null,
                'price' => number_format((float) $attributes['price'], 2, '.', ''),
                'compare_at_price' => isset($attributes['compare_at_price']) && $attributes['compare_at_price'] !== ''
                    ? number_format((float) $attributes['compare_at_price'], 2, '.', '')
                    : null,
                'currency' => 'PHP',
                'status' => $status,
                'is_featured' => (bool) ($attributes['is_featured'] ?? false),
                'featured_rank' => isset($attributes['featured_rank']) && $attributes['featured_rank'] !== ''
                    ? (int) $attributes['featured_rank']
                    : null,
                'published_at' => $publishedAt,
            ];

            if ($existing !== null) {
                $previousStock = $existing->stock_quantity;
                $existing->fill([
                    ...$payload,
                    'stock_quantity' => $desiredStock,
                ])->save();
                $product = $existing;

                if ($desiredStock !== $previousStock) {
                    $this->inventory->recordMovementForLockedProduct(
                        $product,
                        $previousStock,
                        $desiredStock,
                        StockMovementType::ManualAdjustment,
                        $actor,
                        'Stock updated via admin product editor.',
                    );
                }
            } else {
                $product = Product::query()->create([
                    ...$payload,
                    'stock_quantity' => $desiredStock,
                ]);

                if ($desiredStock > 0) {
                    $this->inventory->recordMovementForLockedProduct(
                        $product,
                        0,
                        $desiredStock,
                        StockMovementType::InitialStock,
                        $actor,
                        'Initial stock on product creation.',
                    );
                }
            }

            $this->audit->record(
                $actor,
                $isCreating ? 'product.created' : 'product.updated',
                sprintf('%s product "%s" (%s).', $isCreating ? 'Created' : 'Updated', $product->name, $product->sku),
                'Product',
                $product->id,
                [
                    'sku' => $product->sku,
                    'status' => $product->status->value,
                    'price' => $product->price,
                    'stock_quantity' => $product->stock_quantity,
                ],
            );

            return $product;
        });
    }
}
