<?php

namespace App\Domain\Catalog\Support;

use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Queries\CategoryQuery;

/**
 * Props shared by every storefront page that renders the header/footer shell.
 */
final class StorefrontShell
{
    public function __construct(
        private readonly CategoryQuery $categories,
        private readonly ProductPresenter $presenter,
        private readonly CartService $cart,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        return array_values(
            $this->categories->activeWithProductCounts()
                ->map(fn (Category $category): array => $this->presenter->category(
                    $category,
                    (int) $category->getAttribute('published_products_count'),
                ))
                ->all(),
        );
    }

    /**
     * Server-authoritative cart summary for the active session or authenticated user.
     *
     * @return array{itemCount: int, subtotalFormatted: string|null}
     */
    public function cartSummary(): array
    {
        return $this->cart->summary(request());
    }
}
