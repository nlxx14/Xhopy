<?php

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\Enums\ProductSort;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
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
     * @return Builder<Product>
     */
    private function build(CatalogFilters $filters): Builder
    {
        $query = Product::query()
            ->published()
            ->with(['category:id,name,slug', 'primaryImage'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

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

        $this->applySort($query, $filters->sort);

        return $query;
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
