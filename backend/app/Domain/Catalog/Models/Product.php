<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Support\Money;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Orders\Models\OrderItem;
use Carbon\CarbonInterface;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int|null $store_id
 * @property int $category_id
 * @property string $name
 * @property string $slug
 * @property string $sku
 * @property string|null $short_description
 * @property string|null $description
 * @property string $price
 * @property string|null $compare_at_price
 * @property string $currency
 * @property int $stock_quantity
 * @property list<string>|null $available_colors
 * @property list<string>|null $available_sizes
 * @property array<string, int>|null $variant_stocks
 * @property ProductStatus $status
 * @property bool $is_featured
 * @property int|null $featured_rank
 * @property CarbonInterface|null $published_at
 * @property-read Category $category
 */
#[UseFactory(ProductFactory::class)]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_id',
        'category_id',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'price',
        'compare_at_price',
        'currency',
        'stock_quantity',
        'available_colors',
        'available_sizes',
        'variant_stocks',
        'status',
        'is_featured',
        'featured_rank',
        'published_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'available_colors' => 'array',
            'available_sizes' => 'array',
            'variant_stocks' => 'array',
            'status' => ProductStatus::class,
            'is_featured' => 'boolean',
            'featured_rank' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<ProductReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The image shown on listing cards: the primary image, otherwise the first by sort order.
     *
     * @return HasOne<ProductImage, $this>
     */
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->ofMany([
            'is_primary' => 'max',
            'sort_order' => 'min',
            'id' => 'min',
        ]);
    }

    /**
     * Publicly visible products: active, already published, inside an active category.
     *
     * @param  Builder<Product>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query
            ->where('products.status', ProductStatus::Active)
            ->whereNotNull('products.published_at')
            ->where('products.published_at', '<=', now())
            ->whereIn('products.category_id', Category::query()->active()->select('categories.id'));
    }

    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    public static function variantStockKey(?string $color, ?string $size): string
    {
        $c = $color !== null ? trim($color) : '';
        $s = $size !== null ? trim($size) : '';

        return $c.'|'.$s;
    }

    public function hasVariantStocks(): bool
    {
        return is_array($this->variant_stocks) && $this->variant_stocks !== [];
    }

    public function stockForVariant(?string $color = null, ?string $size = null): int
    {
        if (! $this->hasVariantStocks()) {
            return $this->stock_quantity;
        }

        $stocks = $this->variant_stocks ?? [];
        $key = self::variantStockKey($color, $size);

        if (array_key_exists($key, $stocks)) {
            return max(0, (int) $stocks[$key]);
        }

        $lowerKey = mb_strtolower($key);
        foreach ($stocks as $existingKey => $qty) {
            if (mb_strtolower((string) $existingKey) === $lowerKey) {
                return max(0, (int) $qty);
            }
        }

        return 0;
    }

    public function adjustVariantStock(?string $color, ?string $size, int $delta): void
    {
        if (! $this->hasVariantStocks()) {
            $this->stock_quantity = max(0, $this->stock_quantity + $delta);

            return;
        }

        $stocks = $this->variant_stocks ?? [];
        $targetKey = self::variantStockKey($color, $size);
        $matchedKey = $targetKey;

        if (! array_key_exists($targetKey, $stocks)) {
            $lowerKey = mb_strtolower($targetKey);
            foreach (array_keys($stocks) as $existingKey) {
                if (mb_strtolower((string) $existingKey) === $lowerKey) {
                    $matchedKey = (string) $existingKey;
                    break;
                }
            }
        }

        $currentVariantQty = max(0, (int) ($stocks[$matchedKey] ?? 0));
        $stocks[$matchedKey] = max(0, $currentVariantQty + $delta);
        $this->variant_stocks = $stocks;

        $total = 0;
        foreach ($stocks as $qty) {
            $total += max(0, (int) $qty);
        }
        $this->stock_quantity = $total;
    }

    /**
     * A compare-at price is only meaningful when it is higher than the selling price.
     */
    public function hasDiscount(): bool
    {
        return $this->compare_at_price !== null
            && Money::cents($this->compare_at_price) > Money::cents($this->price);
    }
}
