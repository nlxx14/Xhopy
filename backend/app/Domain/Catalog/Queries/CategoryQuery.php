<?php

namespace App\Domain\Catalog\Queries;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class CategoryQuery
{
    /**
     * Active categories with their count of publicly visible products.
     *
     * @return Collection<int, Category>
     */
    public function activeWithProductCounts(): Collection
    {
        return Category::query()
            ->active()
            ->withCount([
                'products as published_products_count' => fn (Builder $products) => $products
                    ->where('products.status', ProductStatus::Active)
                    ->whereNotNull('products.published_at')
                    ->where('products.published_at', '<=', now()),
            ])
            ->orderBy('categories.sort_order')
            ->orderBy('categories.name')
            ->get();
    }
}
