<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Enums\ProductSort;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Queries\CatalogFilters;
use App\Domain\Catalog\Queries\ProductListingQuery;
use App\Domain\Catalog\Support\ProductPresenter;
use App\Domain\Catalog\Support\StorefrontShell;
use App\Domain\Customer\Services\WishlistService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CatalogIndexRequest;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    /**
     * Public product listing with search, category/price/stock filters, allowlisted sorting and pagination.
     */
    public function index(
        CatalogIndexRequest $request,
        ProductListingQuery $listing,
        ProductPresenter $presenter,
        WishlistService $wishlist,
        StorefrontShell $shell,
    ): Response {
        $filters = $request->filters();
        $paginator = $listing->paginate($filters);
        $wishlistedIds = $wishlist->wishlistedProductIds($request->user());

        $activeCategory = $filters->category !== null
            ? Category::query()->active()->where('slug', $filters->category)->first()
            : null;

        return Inertia::render('catalog/Index', [
            'announcement' => null,
            'categories' => $shell->categories(),
            'cartSummary' => $shell->cartSummary(),
            'products' => collect($paginator->items())
                ->map(fn (Product $product): array => $presenter->summary(
                    $product,
                    in_array($product->id, $wishlistedIds, true),
                ))
                ->values()
                ->all(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => $this->filtersPayload($filters),
            'sortOptions' => ProductSort::options(),
            'perPageOptions' => CatalogFilters::PER_PAGE_OPTIONS,
            'activeCategory' => $activeCategory === null ? null : $presenter->category($activeCategory),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersPayload(CatalogFilters $filters): array
    {
        return [
            'q' => $filters->query,
            'category' => $filters->category,
            'minPrice' => $filters->minPrice,
            'maxPrice' => $filters->maxPrice,
            'inStock' => $filters->inStockOnly,
            'sort' => $filters->sort->value,
            'perPage' => $filters->perPage,
        ];
    }
}
