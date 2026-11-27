<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Queries\CatalogFilters;
use App\Domain\Catalog\Queries\DailyInterestQuery;
use App\Domain\Catalog\Queries\ProductListingQuery;
use App\Domain\Catalog\Support\DemoCatalog;
use App\Domain\Catalog\Support\ProductPresenter;
use App\Domain\Catalog\Support\StorefrontShell;
use App\Domain\Customer\Services\WishlistService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    private const TOP_PRODUCT_LIMIT = 6;

    private const DAILY_DISCOVER_LIMIT = 40;

    /**
     * Render the XHopy storefront home page.
     *
     * Per engineering rule #4 ("No fabricated facts"), production renders only real
     * database records and hides optional campaign modules when none are active.
     * In non-production environments, passing `?preview_demo=1` loads clearly labeled
     * [DEMO] preview items to verify the design system components.
     */
    public function __invoke(
        Request $request,
        StorefrontShell $shell,
        ProductListingQuery $listing,
        DailyInterestQuery $dailyInterest,
        ProductPresenter $presenter,
        WishlistService $wishlist,
        DemoCatalog $demo,
    ): Response {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80', 'alpha_dash'],
            'preview_demo' => ['nullable', 'boolean'],
        ]);

        $searchQuery = isset($validated['q']) ? trim((string) $validated['q']) : '';
        $selectedCategory = isset($validated['category']) ? (string) $validated['category'] : null;
        $isDemoPreview = ! app()->isProduction() && $request->boolean('preview_demo');

        if ($isDemoPreview) {
            $categories = $demo->categories();
            $topProducts = $demo->filterProducts($demo->products(), $searchQuery, $selectedCategory);
            $dailyDiscoverProducts = $demo->products();
            $dailyInterestSection = $demo->dailyInterest();
        } else {
            $wishlistedIds = $wishlist->wishlistedProductIds($request->user());
            $categories = $shell->categories();
            $topProducts = $listing
                ->topSelling(new CatalogFilters(query: $searchQuery, category: $selectedCategory), self::TOP_PRODUCT_LIMIT)
                ->map(fn (Product $product): array => $presenter->summary(
                    $product,
                    in_array($product->id, $wishlistedIds, true),
                ))
                ->values()
                ->all();
            $dailyDiscoverProducts = $listing
                ->randomSelling(self::DAILY_DISCOVER_LIMIT)
                ->map(fn (Product $product): array => $presenter->summary(
                    $product,
                    in_array($product->id, $wishlistedIds, true),
                ))
                ->values()
                ->all();
            $dailyInterestSection = $dailyInterest->current();
        }

        return Inertia::render('Welcome', [
            'announcement' => $isDemoPreview ? [
                'id' => 'demo-announcement',
                'message' => '[DEMO PREVIEW] Free standard delivery on verified orders within Metro Manila.',
                'linkText' => 'Explore Top Products',
                'linkHref' => '#top-products-heading',
            ] : null,
            'hero' => [
                'badgeText' => 'XHopy Official Store',
                'eyebrow' => 'Curated Everyday Essentials',
                'title' => 'Thoughtfully engineered essentials for modern living.',
                'subtitle' => 'Transparent pricing, real-time inventory verification, and seamless checkout built on a fast, accessible storefront.',
                'primaryCtaLabel' => 'Browse Top Products',
                'primaryCtaHref' => '#top-products-heading',
                'secondaryCtaLabel' => 'Explore Categories',
                'secondaryCtaHref' => '#categories-heading',
            ],
            'categories' => $categories,
            'topProducts' => $topProducts,
            'dailyDiscoverProducts' => $dailyDiscoverProducts,
            'dailyInterest' => $dailyInterestSection,
            'cartSummary' => $shell->cartSummary(),
            'searchQuery' => $searchQuery,
            'selectedCategory' => $selectedCategory,
            'isDemoPreview' => $isDemoPreview,
        ]);
    }
}
