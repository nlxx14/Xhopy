<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Support\Money;
use App\Domain\Inventory\Models\StockMovement;
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

    /**
     * A compare-at price is only meaningful when it is higher than the selling price.
     */
    public function hasDiscount(): bool
    {
        return $this->compare_at_price !== null
            && Money::cents($this->compare_at_price) > Money::cents($this->price);
    }
}
