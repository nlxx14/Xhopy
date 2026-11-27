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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $activeCategory = $filters->category !== null
            ? Category::query()->active()->where('slug', $filters->category)->first()
            : null;

        return $this->renderCatalog($request, $filters, $activeCategory, $listing, $presenter, $wishlist, $shell);
    }

    public function category(
        string $categorySlug,
        CatalogIndexRequest $request,
        ProductListingQuery $listing,
        ProductPresenter $presenter,
        WishlistService $wishlist,
        StorefrontShell $shell,
    ): Response {
        $activeCategory = Category::query()->active()->where('slug', $categorySlug)->firstOrFail();
        $validated = $request->validated();
        $validated['category'] = $activeCategory->slug;
        $filters = CatalogFilters::fromValidated($validated);

        return $this->renderCatalog($request, $filters, $activeCategory, $listing, $presenter, $wishlist, $shell);
    }

    private function renderCatalog(
        Request $request,
        CatalogFilters $filters,
        ?Category $activeCategory,
        ProductListingQuery $listing,
        ProductPresenter $presenter,
        WishlistService $wishlist,
        StorefrontShell $shell,
    ): Response {
        $paginator = $listing->paginate($filters);
        $wishlistedIds = $wishlist->wishlistedProductIds($request->user());

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
            'maxCatalogPrice' => $listing->maxPublishedPrice(),
            'sortOptions' => ProductSort::options(),
            'perPageOptions' => CatalogFilters::PER_PAGE_OPTIONS,
            'activeCategory' => $activeCategory === null ? null : $presenter->category($activeCategory),
        ]);
    }

    public function suggestions(
        Request $request,
        ProductListingQuery $listing,
        ProductPresenter $presenter,
    ): JsonResponse {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));
        if ($query === '') {
            return response()->json(['items' => []]);
        }

        $items = $listing
            ->top(new CatalogFilters(query: $query), 6)
            ->map(function (Product $product) use ($presenter): array {
                $summary = $presenter->summary($product);

                return [
                    'id' => $summary['id'],
                    'name' => $summary['name'],
                    'slug' => $summary['slug'],
                    'categoryName' => $summary['categoryName'],
                    'priceFormatted' => $summary['priceFormatted'],
                    'imageUrl' => $summary['imageUrl'],
                    'inStock' => $summary['inStock'],
                    'url' => route('products.show', $product->slug),
                ];
            })
            ->values()
            ->all();

        return response()->json(['items' => $items]);
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
            'onSale' => $filters->onSaleOnly,
            'minRating' => $filters->minRating,
            'sort' => $filters->sort->value,
            'perPage' => $filters->perPage,
        ];
    }
}
