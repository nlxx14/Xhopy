<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Support\ProductPresenter;
use App\Domain\Catalog\Support\StorefrontShell;
use App\Domain\Customer\Services\WishlistService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    private const RELATED_LIMIT = 4;

    /**
     * Product detail page. Only publicly visible products resolve; drafts, archived,
     * unpublished and products in inactive categories return 404.
     */
    public function show(
        Request $request,
        string $slug,
        ProductPresenter $presenter,
        WishlistService $wishlist,
        StorefrontShell $shell,
    ): Response {
        $product = Product::query()
            ->published()
            ->where('products.slug', $slug)
            ->with(['category', 'images'])
            ->firstOrFail();

        $wishlistedIds = $wishlist->wishlistedProductIds($request->user());

        $related = Product::query()
            ->published()
            ->where('products.category_id', $product->category_id)
            ->whereKeyNot($product->getKey())
            ->with(['category:id,name,slug', 'primaryImage'])
            ->orderByDesc('products.is_featured')
            ->orderByDesc('products.published_at')
            ->orderBy('products.id')
            ->limit(self::RELATED_LIMIT)
            ->get();

        return Inertia::render('catalog/Show', [
            'announcement' => null,
            'categories' => $shell->categories(),
            'cartSummary' => $shell->cartSummary(),
            'product' => $presenter->detail(
                $product,
                in_array($product->id, $wishlistedIds, true),
            ),
            'relatedProducts' => $related
                ->map(fn (Product $item): array => $presenter->summary(
                    $item,
                    in_array($item->id, $wishlistedIds, true),
                ))
                ->values()
                ->all(),
        ]);
    }
}
