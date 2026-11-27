<?php

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\Enums\ProductSort;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Orders\Enums\OrderStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the public product listing: published-only, filtered, sorted with an allowlist,
 * with relations eager loaded so rendering never triggers N+1 queries.
 */
final class ProductListingQuery
{
    private const MAX_SEARCH_TERMS = 5;

    private const MAX_TERM_LENGTH = 60;

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(CatalogFilters $filters): LengthAwarePaginator
    {
        return $this->build($filters)->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    /**
     * @return Collection<int, Product>
     */
    public function top(CatalogFilters $filters, int $limit): Collection
    {
        return $this->build($filters)->limit($limit)->get();
    }

    /**
     * Top products ranked by highest non-cancelled sold quantity, falling back to featured & newest.
     *
     * @return Collection<int, Product>
     */
    public function topSelling(CatalogFilters $filters, int $limit): Collection
    {
        $query = $this->baseQuery($filters);

        $query
            ->orderByRaw('COALESCE(sold_quantity, 0) DESC')
            ->orderByDesc('products.is_featured')
            ->orderByRaw('products.featured_rank IS NULL')
            ->orderBy('products.featured_rank')
            ->orderByDesc('products.published_at')
            ->orderBy('products.id');

        return $query->limit($limit)->get();
    }

    /**
     * Randomized active in-stock products currently selling on the storefront.
     *
     * @return Collection<int, Product>
     */
    public function randomSelling(int $limit = 40): Collection
    {
        $query = Product::query()
            ->published()
            ->where('products.stock_quantity', '>', 0)
            ->with(['category:id,name,slug', 'primaryImage', 'images'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        $this->withSoldCount($query);

        return $query->inRandomOrder()->limit($limit)->get();
    }

    public function maxPublishedPrice(): int
    {
        $max = (float) (Product::query()->published()->max('products.price') ?? 0);
        $rounded = (int) (ceil(max(20000, $max) / 1000) * 1000);

        return max(20000, $rounded);
    }

    /**
     * @return Builder<Product>
     */
    private function build(CatalogFilters $filters): Builder
    {
        $query = $this->baseQuery($filters);

        $this->applySort($query, $filters->sort);

        return $query;
    }

    /**
     * @return Builder<Product>
     */
    private function baseQuery(CatalogFilters $filters): Builder
    {
        $query = Product::query()
            ->published()
            ->with(['category:id,name,slug', 'primaryImage', 'images'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        $this->withSoldCount($query);

        if ($filters->category !== null) {
            $query->whereIn(
                'products.category_id',
                Category::query()->active()->where('categories.slug', $filters->category)->select('categories.id'),
            );
        }

        $this->applySearch($query, $filters->query);

        if ($filters->minPrice !== null) {
            $query->where('products.price', '>=', $filters->minPrice);
        }

        if ($filters->maxPrice !== null) {
            $query->where('products.price', '<=', $filters->maxPrice);
        }

        if ($filters->inStockOnly) {
            $query->where('products.stock_quantity', '>', 0);
        }

        if ($filters->onSaleOnly) {
            $query
                ->whereNotNull('products.compare_at_price')
                ->whereColumn('products.compare_at_price', '>', 'products.price');
        }

        if ($filters->minRating !== null) {
            $query->whereRaw(
                '(SELECT AVG(product_reviews.rating) FROM product_reviews WHERE product_reviews.product_id = products.id) >= ?',
                [$filters->minRating],
            );
        }

        return $query;
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function withSoldCount(Builder $query): void
    {
        $query->withSum([
            'orderItems as sold_quantity' => fn (Builder $orderItems) => $orderItems->whereHas(
                'order',
                fn (Builder $orders) => $orders->where('orders.status', '!=', OrderStatus::Cancelled->value),
            ),
        ], 'quantity');
    }

    /**
     * Every whitespace-separated term must match the name, SKU, short description or category name.
     * LIKE wildcards in user input are escaped so "%" or "_" are matched literally.
     *
     * @param  Builder<Product>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $terms = array_slice(
            array_filter(preg_split('/\s+/u', $search) ?: [], fn (string $term): bool => $term !== ''),
            0,
            self::MAX_SEARCH_TERMS,
        );

        foreach ($terms as $term) {
            $like = '%'.$this->escapeLike(mb_substr($term, 0, self::MAX_TERM_LENGTH)).'%';

            $query->where(function (Builder $match) use ($like): void {
                $match->whereRaw("products.name LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("products.sku LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("products.short_description LIKE ? ESCAPE '!'", [$like])
                    ->orWhereIn(
                        'products.category_id',
                        Category::query()->whereRaw("categories.name LIKE ? ESCAPE '!'", [$like])->select('categories.id'),
                    );
            });
        }
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applySort(Builder $query, ProductSort $sort): void
    {
        match ($sort) {
            ProductSort::Featured => $query
                ->orderByDesc('products.is_featured')
                ->orderByRaw('products.featured_rank IS NULL')
                ->orderBy('products.featured_rank')
                ->orderByDesc('products.published_at'),
            ProductSort::Newest => $query->orderByDesc('products.published_at'),
            ProductSort::PriceAsc => $query->orderBy('products.price'),
            ProductSort::PriceDesc => $query->orderByDesc('products.price'),
            ProductSort::NameAsc => $query->orderBy('products.name'),
        };

        // Deterministic tie-breaker keeps pagination stable.
        $query->orderBy('products.id');
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
