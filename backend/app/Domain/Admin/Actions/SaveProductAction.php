<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Services\AdminAuditRecorder;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductImage;
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

            $availableColors = $this->parseOptionList($attributes['available_colors'] ?? null);
            $availableSizes = $this->parseOptionList($attributes['available_sizes'] ?? null);
            $variantStocks = $this->normalizeVariantStocks(
                $attributes['variant_stock_entries'] ?? $attributes['variant_stocks'] ?? null,
                $availableColors,
                $availableSizes,
            );

            $desiredStock = $variantStocks !== null
                ? array_sum($variantStocks)
                : max(0, (int) ($attributes['stock_quantity'] ?? 0));
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
                'available_colors' => $availableColors,
                'available_sizes' => $availableSizes,
                'variant_stocks' => $variantStocks,
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

            if (isset($attributes['image_path']) && is_string($attributes['image_path']) && trim($attributes['image_path']) !== '') {
                ProductImage::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'is_primary' => true,
                        'color_name' => null,
                    ],
                    [
                        'path' => trim($attributes['image_path']),
                        'alt_text' => $product->name,
                        'sort_order' => 0,
                    ],
                );
            }

            $availableColors = $product->available_colors ?? [];
            if ($availableColors === []) {
                ProductImage::query()
                    ->where('product_id', $product->id)
                    ->whereNotNull('color_name')
                    ->delete();
            } else {
                ProductImage::query()
                    ->where('product_id', $product->id)
                    ->whereNotNull('color_name')
                    ->whereNotIn('color_name', $availableColors)
                    ->delete();

                $colorImagePaths = $attributes['color_image_paths'] ?? [];
                if (is_array($colorImagePaths)) {
                    foreach ($availableColors as $index => $colorName) {
                        $rawPath = $colorImagePaths[$colorName] ?? null;
                        if (! is_string($rawPath) || trim($rawPath) === '') {
                            continue;
                        }

                        ProductImage::query()->updateOrCreate(
                            [
                                'product_id' => $product->id,
                                'color_name' => $colorName,
                            ],
                            [
                                'path' => trim($rawPath),
                                'alt_text' => sprintf('%s — %s', $product->name, $colorName),
                                'sort_order' => $index + 1,
                                'is_primary' => false,
                            ],
                        );
                    }
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

    /**
     * @return list<string>|null
     */
    private function parseOptionList(mixed $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        $items = is_array($raw)
            ? $raw
            : (is_string($raw) ? explode(',', $raw) : []);

        $normalized = [];

        foreach ($items as $item) {
            if (! is_scalar($item)) {
                continue;
            }

            $value = Str::limit(trim((string) $item), 80, '');

            if ($value !== '' && ! in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized !== [] ? $normalized : null;
    }

    /**
     * @param  list<string>|null  $availableColors
     * @param  list<string>|null  $availableSizes
     * @return array<string, int>|null
     */
    private function normalizeVariantStocks(
        mixed $rawVariantStocks,
        ?array $availableColors,
        ?array $availableSizes,
    ): ?array {
        if (! is_array($rawVariantStocks) || $rawVariantStocks === []) {
            return null;
        }

        $colors = $availableColors !== null && $availableColors !== [] ? $availableColors : [''];
        $sizes = $availableSizes !== null && $availableSizes !== [] ? $availableSizes : [''];

        if ($colors === [''] && $sizes === ['']) {
            return null;
        }

        $lookup = [];
        if (array_is_list($rawVariantStocks)) {
            foreach ($rawVariantStocks as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $entryColor = isset($entry['color']) && is_string($entry['color']) ? $entry['color'] : '';
                $entrySize = isset($entry['size']) && is_string($entry['size']) ? $entry['size'] : '';
                $entryKey = Product::variantStockKey($entryColor, $entrySize);
                $rawQty = $entry['quantity'] ?? 0;
                $lookup[$entryKey] = max(0, is_numeric($rawQty) ? (int) $rawQty : 0);
            }
        } else {
            foreach ($rawVariantStocks as $rawKey => $rawQty) {
                $lookup[(string) $rawKey] = max(0, is_numeric($rawQty) ? (int) $rawQty : 0);
            }
        }

        $normalized = [];

        foreach ($colors as $color) {
            foreach ($sizes as $size) {
                $key = Product::variantStockKey($color, $size);
                $normalized[$key] = $lookup[$key] ?? 0;
            }
        }

        return $normalized;
    }
}
